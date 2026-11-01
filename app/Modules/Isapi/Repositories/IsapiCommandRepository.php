<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Isapi\Contracts\IIsapiCommandRepository;
use Throwable;

final readonly class IsapiCommandRepository implements IIsapiCommandRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function heartbeat(string $customerId, array $data): array
    {
        $agentId = $this->agentId($data['agentId'] ?? null);
        $now = date('Y-m-d H:i:s');
        $terminalOnline = array_key_exists('terminalOnline', $data)
            ? ($data['terminalOnline'] === null ? null : ((bool) $data['terminalOnline'] ? 1 : 0))
            : null;
        $terminalInfo = isset($data['terminalInfo']) && is_array($data['terminalInfo'])
            ? $this->json($data['terminalInfo'])
            : null;
        $this->database->execute(
            'INSERT INTO IsapiAgents (
                CustomerId, AgentId, DeviceId, ClientVersion, TerminalOnline,
                TerminalLastCheckedAt, TerminalInfo, LastError, LastSeenAt
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                DeviceId = VALUES(DeviceId), ClientVersion = VALUES(ClientVersion),
                TerminalOnline = VALUES(TerminalOnline),
                TerminalLastCheckedAt = VALUES(TerminalLastCheckedAt),
                TerminalInfo = VALUES(TerminalInfo), LastError = VALUES(LastError),
                LastSeenAt = VALUES(LastSeenAt), UpdatedAt = CURRENT_TIMESTAMP',
            [
                $customerId,
                $agentId,
                $this->text($data['deviceId'] ?? null, 100),
                $this->text($data['clientVersion'] ?? null, 50),
                $terminalOnline,
                array_key_exists('terminalOnline', $data) ? $now : null,
                $terminalInfo,
                $this->text($data['lastError'] ?? null, 1000),
                $now,
            ],
        );

        return $this->database->fetchOne(
            'SELECT *,
                    CASE WHEN LastSeenAt >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                         THEN 1 ELSE 0 END AS AgentOnline
             FROM IsapiAgents
             WHERE CustomerId = ? AND AgentId = ? LIMIT 1',
            [$customerId, $agentId],
        ) ?? [];
    }

    public function create(
        string $customerId,
        string $agentId,
        int $terminalIndex,
        ?string $deviceId,
        array $definition,
        ?string $requestedBy,
    ): array {
        $id = $this->uuid();
        $now = date('Y-m-d H:i:s');
        $this->database->insert(
            'IsapiCommands',
            [
                'Id' => $id,
                'CustomerId' => $customerId,
                'AgentId' => $this->agentId($agentId),
                'TerminalIndex' => max(0, min(999, $terminalIndex)),
                'DeviceId' => $this->text($deviceId, 100),
                'Action' => $definition['action'],
                'Parameters' => $this->json(
                    $definition['parameters']
                        ?? $definition['defaultParameters']
                        ?? [],
                ),
                'Status' => 'Pending',
                'AttemptCount' => 0,
                'RequestedBy' => $this->text($requestedBy, 160),
                'ExpiresAt' => date('Y-m-d H:i:s', time() + 300),
                'CreatedAt' => $now,
                'UpdatedAt' => $now,
            ],
        );

        return $this->find($id) ?? [];
    }

    public function claimNext(string $customerId, string $agentId): ?array
    {
        $agentId = $this->agentId($agentId);
        $this->expireAndRelease($customerId, $agentId);
        $this->database->begin();
        try {
            $row = $this->database->fetchOne(
                "SELECT Id FROM IsapiCommands
                 WHERE CustomerId = ? AND AgentId = ? AND Status = 'Pending'
                   AND ExpiresAt > NOW()
                 ORDER BY CreatedAt ASC LIMIT 1 FOR UPDATE",
                [$customerId, $agentId],
            );
            if ($row === null) {
                $this->database->commitTransaction();

                return null;
            }
            $this->database->execute(
                "UPDATE IsapiCommands
                 SET Status = 'Processing', AttemptCount = AttemptCount + 1,
                     ClaimedAt = NOW(),
                     LeaseExpiresAt = DATE_ADD(NOW(), INTERVAL 90 SECOND),
                     UpdatedAt = NOW()
                 WHERE Id = ? AND CustomerId = ? AND Status = 'Pending'",
                [$row['Id'], $customerId],
            );
            $this->database->commitTransaction();

            return $this->findForCustomer((string) $row['Id'], $customerId);
        } catch (Throwable $exception) {
            $this->database->rollbackTransaction();
            throw $exception;
        }
    }

    public function complete(string $id, string $customerId, array $result): ?array
    {
        $existing = $this->findForCustomer($id, $customerId);
        if ($existing === null) {
            return null;
        }
        if (!in_array($existing['Status'], ['Processing', 'Pending'], true)) {
            return $existing;
        }

        $metadata = isset($result['metadata']) && is_array($result['metadata'])
            ? $result['metadata']
            : null;
        $body = isset($result['body']) ? (string) $result['body'] : null;
        if (($existing['Action'] ?? '') === 'get_registered_members') {
            $this->removePasswords($metadata);
            $body = $this->sanitizeBody($body);
        }
        $this->database->update(
            'IsapiCommands',
            [
                'CompletedAt' => date('Y-m-d H:i:s'),
                'DurationMs' => isset($result['durationMs'])
                    ? max(0, min(3600000, (int) $result['durationMs']))
                    : null,
                'ErrorCode' => $this->text($result['errorCode'] ?? null, 100),
                'ErrorMessage' => $this->text($result['errorMessage'] ?? null, 2000),
                'HttpStatus' => isset($result['httpStatus'])
                    ? max(0, min(999, (int) $result['httpStatus']))
                    : null,
                'LeaseExpiresAt' => null,
                'ResponseBody' => $body,
                'ResponseContentType' => $this->text($result['contentType'] ?? null, 150),
                'ResponseMetadata' => $metadata !== null ? $this->json($metadata) : null,
                'Status' => !empty($result['success']) ? 'Completed' : 'Failed',
                'UpdatedAt' => date('Y-m-d H:i:s'),
            ],
            'Id = ? AND CustomerId = ?',
            [$id, $customerId],
        );

        return $this->findForCustomer($id, $customerId);
    }

    public function recent(?string $customerId, int $limit): array
    {
        $where = $customerId !== null ? 'WHERE c.CustomerId = ?' : '';
        $parameters = $customerId !== null ? [$customerId] : [];
        $parameters[] = max(1, min(200, $limit));

        return $this->database->fetchAll(
            'SELECT c.Id, c.CustomerId, c.AgentId, c.TerminalIndex, c.DeviceId,
                    c.Action, c.Status, c.AttemptCount, c.HttpStatus,
                    c.ResponseContentType, c.ErrorCode, c.ErrorMessage,
                    c.DurationMs, c.RequestedBy, c.ClaimedAt, c.CompletedAt,
                    c.ExpiresAt, c.CreatedAt, c.UpdatedAt,
                    cu.Name AS CustomerName
             FROM IsapiCommands c
             JOIN Customers cu ON cu.Id = c.CustomerId
             ' . $where . '
             ORDER BY c.CreatedAt DESC LIMIT ?',
            $parameters,
        );
    }

    public function agents(): array
    {
        return $this->database->fetchAll(
            'SELECT a.*, c.Name AS CustomerName,
                    CASE WHEN a.LastSeenAt >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                         THEN 1 ELSE 0 END AS AgentOnline
             FROM IsapiAgents a
             JOIN Customers c ON c.Id = a.CustomerId
             ORDER BY a.LastSeenAt DESC',
        );
    }

    public function find(string $id): ?array
    {
        return $this->database->fetchOne(
            'SELECT c.*, cu.Name AS CustomerName
             FROM IsapiCommands c
             JOIN Customers cu ON cu.Id = c.CustomerId
             WHERE c.Id = ? LIMIT 1',
            [$id],
        );
    }

    public function findForCustomer(string $id, string $customerId): ?array
    {
        return $this->database->fetchOne(
            'SELECT * FROM IsapiCommands WHERE Id = ? AND CustomerId = ? LIMIT 1',
            [$id, $customerId],
        );
    }

    private function expireAndRelease(string $customerId, string $agentId): void
    {
        $this->database->execute(
            "UPDATE IsapiCommands SET Status = 'Expired', UpdatedAt = NOW()
             WHERE CustomerId = ? AND Status = 'Pending' AND ExpiresAt <= NOW()",
            [$customerId],
        );
        $this->database->execute(
            "UPDATE IsapiCommands
             SET Status = CASE WHEN AttemptCount >= 3 THEN 'Failed' ELSE 'Pending' END,
                 ErrorCode = CASE WHEN AttemptCount >= 3 THEN 'lease_expired' ELSE ErrorCode END,
                 LeaseExpiresAt = NULL, UpdatedAt = NOW()
             WHERE CustomerId = ? AND AgentId = ? AND Status = 'Processing'
               AND LeaseExpiresAt IS NOT NULL AND LeaseExpiresAt <= NOW()",
            [$customerId, $agentId],
        );
    }

    private function agentId(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? 'desktop-main' : mb_substr($value, 0, 100);
    }

    private function text(mixed $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    private function json(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function sanitizeBody(?string $body): ?string
    {
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return $body;
        }
        $this->removePasswords($decoded);

        return $this->json($decoded);
    }

    private function removePasswords(?array &$value): void
    {
        if ($value === null) {
            return;
        }
        foreach ($value as $key => &$item) {
            if (is_string($key) && strtolower($key) === 'password') {
                unset($value[$key]);
            } elseif (is_array($item)) {
                $this->removePasswords($item);
            }
        }
        unset($item);
    }
}
