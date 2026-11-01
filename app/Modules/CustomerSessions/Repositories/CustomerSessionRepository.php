<?php

declare(strict_types=1);

namespace App\Modules\CustomerSessions\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\CustomerSessions\Contracts\ICustomerSessionRepository;

final readonly class CustomerSessionRepository implements ICustomerSessionRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function purgeExpired(int $graceSeconds): void
    {
        $sql = 'UPDATE CustomerSessions
                SET Status = "inactive",
                    EndedAt = NOW(),
                    EndedReason = "timeout",
                    UpdatedAt = NOW()
                WHERE Status = "active" AND LastSeen < DATE_SUB(NOW(), INTERVAL ? SECOND)';

        $this->db->execute($sql, [$graceSeconds]);
    }

    public function findActive(
        string $customerId,
        ?string $deviceId,
        int $graceSeconds,
    ): ?array
    {
        $conditions = [
            'CustomerId = ?',
            'Status = ?',
        ];
        $params = [
            $customerId,
            'active',
        ];

        if ($deviceId !== null) {
            $conditions[] = 'DeviceId = ?';
            $params[] = $deviceId;
        }

        $conditions[] = 'LastSeen >= DATE_SUB(NOW(), INTERVAL ? SECOND)';
        $params[] = $graceSeconds;

        $sql = sprintf(
            'SELECT * FROM CustomerSessions WHERE %s ORDER BY LastSeen DESC LIMIT 1',
            implode(' AND ', $conditions),
        );
        $row = $this->db->fetchOne($sql, $params);

        return $row !== null
            ? $this->hydrate($row, $graceSeconds)
            : null;
    }

    public function start(array $sessionData, int $graceSeconds): array
    {
        $sessionId = bin2hex(random_bytes(16));
        $now = $this->now();
        $metadata = isset($sessionData['metadata']) && is_array($sessionData['metadata'])
            ? $sessionData['metadata']
            : null;
        $encodedMetadata = $this->encodeMetadata($metadata);

        $this->db->insert(
            'CustomerSessions',
            [
                'Id' => $sessionId,
                'CustomerId' => $sessionData['customerId'],
                'DeviceId' => $sessionData['deviceId'] ?? null,
                'AppVersion' => $sessionData['appVersion'] ?? null,
                'IpAddress' => $sessionData['ipAddress'] ?? null,
                'Metadata' => $encodedMetadata,
                'Status' => 'active',
                'StartedAt' => $now,
                'LastSeen' => $now,
                'CreatedAt' => $now,
                'UpdatedAt' => $now,
            ],
        );

        if (!empty($sessionData['customerId'])) {
            $this->db->update(
                'Customers',
                [
                    'LastSeen' => $now,
                    'UpdatedAt' => $now,
                ],
                'Id = ?',
                [$sessionData['customerId']],
            );
        }

        $row = $this->db->fetchOne(
            'SELECT * FROM CustomerSessions WHERE Id = ?',
            [$sessionId],
        );

        if ($row === null) {
            $row = [
                'Id' => $sessionId,
                'CustomerId' => $sessionData['customerId'],
                'DeviceId' => $sessionData['deviceId'] ?? null,
                'AppVersion' => $sessionData['appVersion'] ?? null,
                'IpAddress' => $sessionData['ipAddress'] ?? null,
                'Metadata' => $encodedMetadata,
                'Status' => 'active',
                'StartedAt' => $now,
                'LastSeen' => $now,
                'EndedAt' => null,
                'EndedReason' => null,
            ];
        }

        return $this->hydrate($row, $graceSeconds);
    }

    public function heartbeat(
        string $sessionId,
        array $updates,
        int $graceSeconds,
    ): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM CustomerSessions WHERE Id = ?',
            [$sessionId],
        );

        if ($row === null) {
            return null;
        }

        $now = $this->now();
        $update = [
            'LastSeen' => $now,
            'Status' => 'active',
            'UpdatedAt' => $now,
        ];

        if (isset($updates['metadata']) && is_array($updates['metadata'])) {
            $currentMetadata = $this->decodeMetadata($row['Metadata'] ?? null);
            $mergedMetadata = array_merge(
                $currentMetadata,
                $updates['metadata'],
            );
            $update['Metadata'] = $this->encodeMetadata($mergedMetadata);
        }

        if (isset($updates['appVersion'])) {
            $update['AppVersion'] = $updates['appVersion'];
        }

        if (isset($updates['ipAddress'])) {
            $update['IpAddress'] = $updates['ipAddress'];
        }

        $this->db->update(
            'CustomerSessions',
            $update,
            'Id = ?',
            [$sessionId],
        );

        if (!empty($row['CustomerId'])) {
            $this->db->update(
                'Customers',
                [
                    'LastSeen' => $update['LastSeen'],
                    'UpdatedAt' => $update['UpdatedAt'],
                ],
                'Id = ?',
                [$row['CustomerId']],
            );
        }

        $updatedRow = $this->db->fetchOne(
            'SELECT * FROM CustomerSessions WHERE Id = ?',
            [$sessionId],
        );

        return $updatedRow !== null
            ? $this->hydrate($updatedRow, $graceSeconds)
            : null;
    }

    public function end(string $sessionId, string $reason): bool
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM CustomerSessions WHERE Id = ?',
            [$sessionId],
        );

        if ($row === null) {
            return false;
        }

        $now = $this->now();

        $this->db->update(
            'CustomerSessions',
            [
                'Status' => 'inactive',
                'EndedAt' => $now,
                'EndedReason' => $reason,
                'UpdatedAt' => $now,
            ],
            'Id = ?',
            [$sessionId],
        );

        return true;
    }

    public function list(?string $customerId, int $graceSeconds): array
    {
        $conditions = [];
        $params = [];

        if ($customerId !== null && $customerId !== '') {
            $conditions[] = 'CustomerId = ?';
            $params[] = $customerId;
        }

        $whereClause = $conditions === []
            ? ''
            : 'WHERE ' . implode(' AND ', $conditions);
        $sql = sprintf(
            'SELECT * FROM CustomerSessions %s ORDER BY LastSeen DESC',
            $whereClause,
        );
        $rows = $this->db->fetchAll($sql, $params);

        return array_map(
            fn (array $row): array => $this->hydrate($row, $graceSeconds),
            $rows,
        );
    }

    /** @return array<string, mixed> */
    private function hydrate(array $row, int $graceSeconds): array
    {
        $lastSeen = $this->toTimestamp($row['LastSeen'] ?? null);
        $session = [
            'sessionId' => $row['Id'],
            'customerId' => $row['CustomerId'],
            'deviceId' => $row['DeviceId'],
            'appVersion' => $row['AppVersion'],
            'ipAddress' => $row['IpAddress'],
            'metadata' => $this->decodeMetadata($row['Metadata'] ?? null),
            'status' => $row['Status'],
            'startedAt' => $this->toTimestamp($row['StartedAt'] ?? null),
            'lastSeen' => $lastSeen,
            'endedAt' => $this->toTimestamp($row['EndedAt'] ?? null),
            'endedReason' => $row['EndedReason'] ?? null,
        ];

        if ($lastSeen !== null) {
            $session['isExpired'] = (time() - $lastSeen) > $graceSeconds;
        }

        return $session;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function toTimestamp(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false
            ? null
            : $timestamp;
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(?string $encodedMetadata): array
    {
        if ($encodedMetadata === null || $encodedMetadata === '') {
            return [];
        }

        $metadata = json_decode($encodedMetadata, true);

        return json_last_error() === JSON_ERROR_NONE
            ? (array) $metadata
            : [];
    }

    /** @param array<string, mixed>|null $metadata */
    private function encodeMetadata(?array $metadata): ?string
    {
        if ($metadata === null) {
            return null;
        }

        $encodedMetadata = json_encode(
            $metadata,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return json_last_error() === JSON_ERROR_NONE
            ? $encodedMetadata
            : null;
    }
}
