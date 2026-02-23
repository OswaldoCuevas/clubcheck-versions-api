<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class CustomerSessionModel extends Model
{
    private int $defaultHeartbeatGrace = 180; // segundos

    protected function initialize()
    {
        // No additional setup required when using database storage.
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function toTimestamp(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $time = strtotime($value);

        return $time === false ? null : $time;
    }

    private function decodeMetadata(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? (array) $decoded : [];
    }

    private function encodeMetadata(?array $metadata): ?string
    {
        if ($metadata === null) {
            return null;
        }

        $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return json_last_error() === JSON_ERROR_NONE ? $json : null;
    }

    private function hydrateSession(array $row, ?int $graceSeconds = null): array
    {
        $session = [
            'sessionId' => $row['Id'],
            'customerId' => $row['CustomerId'],
            'deviceId' => $row['DeviceId'],
            'appVersion' => $row['AppVersion'],
            'ipAddress' => $row['IpAddress'],
            'metadata' => $this->decodeMetadata($row['Metadata'] ?? null),
            'status' => $row['Status'],
            'startedAt' => $this->toTimestamp($row['StartedAt'] ?? null),
            'lastSeen' => $this->toTimestamp($row['LastSeen'] ?? null),
            'endedAt' => $this->toTimestamp($row['EndedAt'] ?? null),
            'endedReason' => $row['EndedReason'] ?? null,
        ];

        if ($graceSeconds !== null && $session['lastSeen'] !== null) {
            $session['isExpired'] = (time() - $session['lastSeen']) > $graceSeconds;
        }

        return $session;
    }

    public function startSession(array $payload): array
    {
        $sessionId = $this->generateSessionId();
        $now = $this->now();

        $metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : null;

        $this->db->insert('CustomerSessions', [
            'Id' => $sessionId,
            'CustomerId' => $payload['customerId'],
            'DeviceId' => $payload['deviceId'] ?? null,
            'AppVersion' => $payload['appVersion'] ?? null,
            'IpAddress' => $payload['ipAddress'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            'Metadata' => $this->encodeMetadata($metadata),
            'Status' => 'active',
            'StartedAt' => $now,
            'LastSeen' => $now,
            'CreatedAt' => $now,
            'UpdatedAt' => $now,
        ]);

        if (!empty($payload['customerId'])) {
            $this->db->update(
                'Customers',
                ['LastSeen' => $now, 'UpdatedAt' => $now],
                'Id = ?',
                [$payload['customerId']]
            );
        }

        $row = $this->db->fetchOne('SELECT * FROM CustomerSessions WHERE Id = ?', [$sessionId]);

        if ($row === null) {
            $row = [
                'Id' => $sessionId,
                'CustomerId' => $payload['customerId'],
                'DeviceId' => $payload['deviceId'] ?? null,
                'AppVersion' => $payload['appVersion'] ?? null,
                'IpAddress' => $payload['ipAddress'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                'Metadata' => $this->encodeMetadata($metadata),
                'Status' => 'active',
                'StartedAt' => $now,
                'LastSeen' => $now,
                'EndedAt' => null,
                'EndedReason' => null,
            ];
        }

        return $this->hydrateSession($row, $this->defaultHeartbeatGrace);
    }

    public function findActiveSession(string $customerId, ?string $deviceId = null, ?int $graceSeconds = null): ?array
    {
        $grace = $graceSeconds ?? $this->defaultHeartbeatGrace;

        $conditions = ['CustomerId = ?', 'Status = ?'];
        $params = [$customerId, 'active'];

        if ($deviceId !== null) {
            $conditions[] = 'DeviceId = ?';
            $params[] = $deviceId;
        }

        $conditions[] = 'LastSeen >= DATE_SUB(NOW(), INTERVAL ? SECOND)';
        $params[] = $grace;

        $sql = sprintf(
            'SELECT * FROM CustomerSessions WHERE %s ORDER BY LastSeen DESC LIMIT 1',
            implode(' AND ', $conditions)
        );

        $row = $this->db->fetchOne($sql, $params);

        return $row ? $this->hydrateSession($row, $grace) : null;
    }

    public function heartbeat(string $sessionId, array $updates = []): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM CustomerSessions WHERE Id = ?', [$sessionId]);

        if ($row === null) {
            return null;
        }

        $update = [
            'LastSeen' => $this->now(),
            'Status' => 'active',
            'UpdatedAt' => $this->now(),
        ];

        if (isset($updates['metadata']) && is_array($updates['metadata'])) {
            $currentMetadata = $this->decodeMetadata($row['Metadata'] ?? null);
            $update['Metadata'] = $this->encodeMetadata(array_merge($currentMetadata, $updates['metadata']));
        }

        if (isset($updates['appVersion'])) {
            $update['AppVersion'] = $updates['appVersion'];
        }

        if (isset($updates['ipAddress'])) {
            $update['IpAddress'] = $updates['ipAddress'];
        }

        $this->db->update('CustomerSessions', $update, 'Id = ?', [$sessionId]);

        if (!empty($row['CustomerId'])) {
            $this->db->update(
                'Customers',
                ['LastSeen' => $update['LastSeen'], 'UpdatedAt' => $update['UpdatedAt']],
                'Id = ?',
                [$row['CustomerId']]
            );
        }

        $updated = $this->db->fetchOne('SELECT * FROM CustomerSessions WHERE Id = ?', [$sessionId]);

        return $updated ? $this->hydrateSession($updated, $this->defaultHeartbeatGrace) : null;
    }

    public function endSession(string $sessionId, string $reason = 'disconnected'): bool
    {
        $row = $this->db->fetchOne('SELECT * FROM CustomerSessions WHERE Id = ?', [$sessionId]);

        if ($row === null) {
            return false;
        }

        $this->db->update(
            'CustomerSessions',
            [
                'Status' => 'inactive',
                'EndedAt' => $this->now(),
                'EndedReason' => $reason,
                'UpdatedAt' => $this->now(),
            ],
            'Id = ?',
            [$sessionId]
        );

        return true;
    }

    public function purgeExpired(?int $graceSeconds = null): int
    {
        $grace = $graceSeconds ?? $this->defaultHeartbeatGrace;

        $sql = 'UPDATE CustomerSessions
                SET Status = "inactive",
                    EndedAt = NOW(),
                    EndedReason = "timeout",
                    UpdatedAt = NOW()
                WHERE Status = "active" AND LastSeen < DATE_SUB(NOW(), INTERVAL ? SECOND)';

        $this->db->execute_query($sql, [$grace]);

        return $this->db->affected_rows;
    }

    public function getSession(string $sessionId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM CustomerSessions WHERE Id = ?', [$sessionId]);

        return $row ? $this->hydrateSession($row, $this->defaultHeartbeatGrace) : null;
    }

    public function getSessions(array $filters = [], ?int $graceSeconds = null): array
    {
        $grace = $graceSeconds ?? $this->defaultHeartbeatGrace;

        $conditions = [];
        $params = [];

        foreach ($filters as $column => $value) {
            $conditions[] = sprintf('%s = ?', $this->mapFilterColumn($column));
            $params[] = $value;
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $sql = sprintf('SELECT * FROM CustomerSessions %s ORDER BY LastSeen DESC', $whereClause);

        $rows = $this->db->fetchAll($sql, $params);

        return array_map(fn ($row) => $this->hydrateSession($row, $grace), $rows);
    }

    private function mapFilterColumn(string $key): string
    {
        return match ($key) {
            'customerId' => 'CustomerId',
            'deviceId' => 'DeviceId',
            'status' => 'Status',
            default => $key,
        };
    }
}
