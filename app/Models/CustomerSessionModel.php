<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class CustomerSessionModel extends Model
{
    private string $sessionFile;
    private int $defaultHeartbeatGrace = 180; // segundos

    protected function initialize()
    {
        $this->sessionFile = __DIR__ . '/../../storage/customer_sessions.json';
        $directory = dirname($this->sessionFile);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (!file_exists($this->sessionFile)) {
            $this->saveJsonFile($this->sessionFile, [
                'sessions' => []
            ]);
        }
    }

    private function loadSessions(): array
    {
        $data = $this->loadJsonFile($this->sessionFile);

        if (!is_array($data)) {
            $data = ['sessions' => []];
        }

        if (!isset($data['sessions']) || !is_array($data['sessions'])) {
            $data['sessions'] = [];
        }

        return $data;
    }

    private function saveSessions(array $data): void
    {
        $this->saveJsonFile($this->sessionFile, $data);
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function startSession(array $payload): array
    {
        $data = $this->loadSessions();
        $sessionId = $this->generateSessionId();
        $now = time();

        $session = [
            'sessionId' => $sessionId,
            'customerId' => $payload['customerId'] ?? null,
            'deviceId' => $payload['deviceId'] ?? null,
            'appVersion' => $payload['appVersion'] ?? null,
            'ipAddress' => $payload['ipAddress'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            'metadata' => $payload['metadata'] ?? [],
            'startedAt' => $now,
            'lastSeen' => $now,
            'status' => 'active'
        ];

        $data['sessions'][$sessionId] = $session;
        $this->saveSessions($data);

        return $session;
    }

    public function findActiveSession(string $customerId, ?string $deviceId = null, int $graceSeconds = null): ?array
    {
        $data = $this->loadSessions();
        $now = time();
        $grace = $graceSeconds ?? $this->defaultHeartbeatGrace;

        foreach ($data['sessions'] as $session) {
            $sessionCustomer = $session['customerId'] ?? null;
            $sessionDevice = $session['deviceId'] ?? null;
            $status = $session['status'] ?? 'inactive';
            $lastSeen = $session['lastSeen'] ?? 0;

            if ($sessionCustomer !== $customerId) {
                continue;
            }

            if ($deviceId !== null && $sessionDevice !== $deviceId) {
                continue;
            }

            if ($status !== 'active') {
                continue;
            }

            if (($now - $lastSeen) > $grace) {
                continue;
            }

            return $session;
        }

        return null;
    }

    public function heartbeat(string $sessionId, array $updates = []): ?array
    {
        $data = $this->loadSessions();

        if (!isset($data['sessions'][$sessionId])) {
            return null;
        }

        $session = $data['sessions'][$sessionId];
        $session['lastSeen'] = time();
        $session['status'] = 'active';

        if (isset($updates['metadata']) && is_array($updates['metadata'])) {
            $session['metadata'] = array_merge($session['metadata'] ?? [], $updates['metadata']);
        }

        if (isset($updates['appVersion'])) {
            $session['appVersion'] = $updates['appVersion'];
        }

        if (isset($updates['ipAddress'])) {
            $session['ipAddress'] = $updates['ipAddress'];
        }

        $data['sessions'][$sessionId] = $session;
        $this->saveSessions($data);

        return $session;
    }

    public function endSession(string $sessionId, string $reason = 'disconnected'): bool
    {
        $data = $this->loadSessions();

        if (!isset($data['sessions'][$sessionId])) {
            return false;
        }

        $session = $data['sessions'][$sessionId];
        $session['status'] = 'inactive';
        $session['endedAt'] = time();
        $session['endedReason'] = $reason;

        $data['sessions'][$sessionId] = $session;
        $this->saveSessions($data);

        return true;
    }

    public function purgeExpired(int $graceSeconds = null): int
    {
        $data = $this->loadSessions();
        $grace = $graceSeconds ?? $this->defaultHeartbeatGrace;
        $now = time();
        $changes = 0;

        foreach ($data['sessions'] as $sessionId => $session) {
            $lastSeen = $session['lastSeen'] ?? 0;
            $status = $session['status'] ?? 'active';

            if ($status === 'active' && ($now - $lastSeen) > $grace) {
                $data['sessions'][$sessionId]['status'] = 'inactive';
                $data['sessions'][$sessionId]['endedAt'] = $now;
                $data['sessions'][$sessionId]['endedReason'] = 'timeout';
                $changes++;
            }
        }

        if ($changes > 0) {
            $this->saveSessions($data);
        }

        return $changes;
    }

    public function getSession(string $sessionId): ?array
    {
        $data = $this->loadSessions();
        return $data['sessions'][$sessionId] ?? null;
    }

    public function getSessions(array $filters = [], int $graceSeconds = null): array
    {
        $data = $this->loadSessions();
        $grace = $graceSeconds ?? $this->defaultHeartbeatGrace;
        $now = time();

        $sessions = array_map(function ($session) use ($now, $grace) {
            $lastSeen = $session['lastSeen'] ?? 0;
            $session['isExpired'] = ($now - $lastSeen) > $grace;
            return $session;
        }, $data['sessions']);

        if (!empty($filters)) {
            $sessions = array_filter($sessions, function ($session) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (!isset($session[$key]) || $session[$key] != $value) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Reindex array
        return array_values($sessions);
    }
}
