<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/../../utils/GlobalFunctions.php';

use Core\Model;

class IsapiCommandModel extends Model
{
    public const STATUS_PENDING = 'Pending';
    public const STATUS_PROCESSING = 'Processing';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_FAILED = 'Failed';

    public function heartbeat(string $customerId, array $data): array
    {
        $agentId = $this->agentId($data['agentId'] ?? null);
        $deviceId = $this->nullableText($data['deviceId'] ?? null, 100);
        $clientVersion = $this->nullableText($data['clientVersion'] ?? null, 50);
        $terminalOnline = array_key_exists('terminalOnline', $data)
            ? ($data['terminalOnline'] === null ? null : ((bool) $data['terminalOnline'] ? 1 : 0))
            : null;
        $terminalInfo = isset($data['terminalInfo']) && is_array($data['terminalInfo'])
            ? $this->json($data['terminalInfo'])
            : null;
        $lastError = $this->nullableText($data['lastError'] ?? null, 1000);
        $now = date('Y-m-d H:i:s');

        $this->db->execute_query(
            'INSERT INTO IsapiAgents
                (CustomerId, AgentId, DeviceId, ClientVersion, TerminalOnline,
                 TerminalLastCheckedAt, TerminalInfo, LastError, LastSeenAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                DeviceId = VALUES(DeviceId), ClientVersion = VALUES(ClientVersion),
                TerminalOnline = VALUES(TerminalOnline),
                TerminalLastCheckedAt = VALUES(TerminalLastCheckedAt),
                TerminalInfo = VALUES(TerminalInfo), LastError = VALUES(LastError),
                LastSeenAt = VALUES(LastSeenAt), UpdatedAt = CURRENT_TIMESTAMP',
            [
                $customerId,
                $agentId,
                $deviceId,
                $clientVersion,
                $terminalOnline,
                array_key_exists('terminalOnline', $data) ? $now : null,
                $terminalInfo,
                $lastError,
                $now,
            ]
        );

        return $this->agent($customerId, $agentId) ?? [];
    }

    public function create(
        string $customerId,
        string $agentId,
        int $terminalIndex,
        ?string $deviceId,
        array $definition,
        ?string $requestedBy
    ): array {
        $id = \GlobalFunctions::generateUuid();
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + 300);

        $this->db->insert('IsapiCommands', [
            'Action' => $definition['action'],
            'AgentId' => $this->agentId($agentId),
            'CreatedAt' => $now,
            'CustomerId' => $customerId,
            'DeviceId' => $this->nullableText($deviceId, 100),
            'ExpiresAt' => $expiresAt,
            'Id' => $id,
            'Parameters' => $this->json($definition['parameters'] ?? $definition['defaultParameters'] ?? []),
            'RequestedBy' => $this->nullableText($requestedBy, 160),
            'Status' => self::STATUS_PENDING,
            'TerminalIndex' => max(0, min(999, $terminalIndex)),
            'UpdatedAt' => $now,
        ]);

        return $this->find($id) ?? [];
    }

    public function claimNext(string $customerId, string $agentId): ?array
    {
        $agentId = $this->agentId($agentId);
        $this->expireOldCommands($customerId);
        $this->releaseExpiredLeases($customerId, $agentId);

        $this->db->begin();
        try {
            $row = $this->db->fetchOne(
                'SELECT Id FROM IsapiCommands
                 WHERE CustomerId = ? AND AgentId = ? AND Status = ? AND ExpiresAt > NOW()
                 ORDER BY CreatedAt ASC LIMIT 1 FOR UPDATE',
                [$customerId, $agentId, self::STATUS_PENDING]
            );

            if (!$row) {
                $this->db->commitTransaction();
                return null;
            }

            $this->db->execute_query(
                'UPDATE IsapiCommands
                 SET Status = ?, AttemptCount = AttemptCount + 1, ClaimedAt = NOW(),
                     LeaseExpiresAt = DATE_ADD(NOW(), INTERVAL 90 SECOND), UpdatedAt = NOW()
                 WHERE Id = ? AND CustomerId = ? AND Status = ?',
                [self::STATUS_PROCESSING, $row['Id'], $customerId, self::STATUS_PENDING]
            );
            $this->db->commitTransaction();

            return $this->findForCustomer($row['Id'], $customerId);
        } catch (\Throwable $e) {
            $this->db->rollbackTransaction();
            throw $e;
        }
    }

    public function complete(string $id, string $customerId, array $result): ?array
    {
        $existing = $this->findForCustomer($id, $customerId);
        if (!$existing) {
            return null;
        }
        if (!in_array($existing['Status'], [self::STATUS_PROCESSING, self::STATUS_PENDING], true)) {
            return $existing;
        }

        $success = (bool) ($result['success'] ?? false);
        $responseBody = isset($result['body']) ? (string) $result['body'] : null;
        $metadata = isset($result['metadata']) && is_array($result['metadata'])
            ? $this->json($result['metadata'])
            : null;

        $this->db->update('IsapiCommands', [
            'CompletedAt' => date('Y-m-d H:i:s'),
            'DurationMs' => isset($result['durationMs']) ? max(0, min(3600000, (int) $result['durationMs'])) : null,
            'ErrorCode' => $this->nullableText($result['errorCode'] ?? null, 100),
            'ErrorMessage' => $this->nullableText($result['errorMessage'] ?? null, 2000),
            'HttpStatus' => isset($result['httpStatus']) ? max(0, min(999, (int) $result['httpStatus'])) : null,
            'LeaseExpiresAt' => null,
            'ResponseBody' => $responseBody,
            'ResponseContentType' => $this->nullableText($result['contentType'] ?? null, 150),
            'ResponseMetadata' => $metadata,
            'Status' => $success ? self::STATUS_COMPLETED : self::STATUS_FAILED,
            'UpdatedAt' => date('Y-m-d H:i:s'),
        ], 'Id = ? AND CustomerId = ?', [$id, $customerId]);

        return $this->findForCustomer($id, $customerId);
    }

    public function listRecent(?string $customerId = null, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $params = [];
        $where = '';
        if ($customerId !== null && $customerId !== '') {
            $where = 'WHERE c.CustomerId = ?';
            $params[] = $customerId;
        }
        $params[] = $limit;

        return $this->db->fetchAll(
            'SELECT c.Id, c.CustomerId, c.AgentId, c.TerminalIndex, c.DeviceId, c.Action, c.Parameters,
                    c.Status, c.AttemptCount,
                    c.HttpStatus, c.ResponseContentType, c.ErrorCode, c.ErrorMessage,
                    c.DurationMs, c.RequestedBy, c.ClaimedAt, c.CompletedAt,
                    c.ExpiresAt, c.CreatedAt, c.UpdatedAt, cu.Name AS CustomerName
             FROM IsapiCommands c
             JOIN Customers cu ON cu.Id = c.CustomerId
             ' . $where . '
             ORDER BY c.CreatedAt DESC LIMIT ?',
            $params
        );
    }

    public function listAgents(): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, c.Name AS CustomerName,
                    CASE WHEN a.LastSeenAt >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END AS AgentOnline
             FROM IsapiAgents a
             JOIN Customers c ON c.Id = a.CustomerId
             ORDER BY a.LastSeenAt DESC'
        );
    }

    public function find(string $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT c.*, cu.Name AS CustomerName
             FROM IsapiCommands c JOIN Customers cu ON cu.Id = c.CustomerId
             WHERE c.Id = ? LIMIT 1',
            [$id]
        );
    }

    public function findForCustomer(string $id, string $customerId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM IsapiCommands WHERE Id = ? AND CustomerId = ? LIMIT 1',
            [$id, $customerId]
        );
    }

    private function agent(string $customerId, string $agentId): ?array
    {
        return $this->db->fetchOne(
            'SELECT *, CASE WHEN LastSeenAt >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 1 ELSE 0 END AS AgentOnline
             FROM IsapiAgents WHERE CustomerId = ? AND AgentId = ? LIMIT 1',
            [$customerId, $agentId]
        );
    }

    private function expireOldCommands(string $customerId): void
    {
        $this->db->execute_query(
            "UPDATE IsapiCommands SET Status = 'Expired', UpdatedAt = NOW()
             WHERE CustomerId = ? AND Status = 'Pending' AND ExpiresAt <= NOW()",
            [$customerId]
        );
    }

    private function releaseExpiredLeases(string $customerId, string $agentId): void
    {
        $this->db->execute_query(
            "UPDATE IsapiCommands
             SET Status = CASE WHEN AttemptCount >= 3 THEN 'Failed' ELSE 'Pending' END,
                 ErrorCode = CASE WHEN AttemptCount >= 3 THEN 'lease_expired' ELSE ErrorCode END,
                 ErrorMessage = CASE WHEN AttemptCount >= 3 THEN 'El agente no reporto el resultado despues de 3 intentos.' ELSE ErrorMessage END,
                 LeaseExpiresAt = NULL, UpdatedAt = NOW()
             WHERE CustomerId = ? AND AgentId = ? AND Status = 'Processing'
               AND LeaseExpiresAt IS NOT NULL AND LeaseExpiresAt <= NOW()",
            [$customerId, $agentId]
        );
    }

    private function agentId($value): string
    {
        $value = trim((string) $value);
        return $value === '' ? 'desktop-main' : mb_substr($value, 0, 100);
    }

    private function nullableText($value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    private function json(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \InvalidArgumentException('No fue posible serializar los metadatos.');
        }
        return $json;
    }
}
