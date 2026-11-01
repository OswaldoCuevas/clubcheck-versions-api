<?php

declare(strict_types=1);

namespace App\Modules\Customers\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Customers\Contracts\IDesktopSyncRepository;
use Throwable;

final readonly class DesktopSyncRepository implements IDesktopSyncRepository
{
    public function __construct(
        private IDatabaseConnection $db,
        private DesktopBulkCatalog $catalog,
    ) {
    }

    public function bulkNames(): array
    {
        return $this->catalog->names();
    }

    public function pull(
        string $bulkName,
        string $customerApiId,
        bool $includeRemoved,
    ): array {
        return $this->pullRows(
            $bulkName,
            $customerApiId,
            $includeRemoved,
            true,
        );
    }

    public function pullForAdmin(
        string $bulkName,
        string $customerApiId,
        bool $includeRemoved,
    ): array {
        return $this->pullRows(
            $bulkName,
            $customerApiId,
            $includeRemoved,
            false,
        );
    }

    /** @return list<array<string, mixed>> */
    private function pullRows(
        string $bulkName,
        string $customerApiId,
        bool $includeRemoved,
        bool $attachUserPhotos,
    ): array {
        $customerApiId = trim($customerApiId);

        if ($customerApiId === '') {
            return [];
        }

        $definition = $this->catalog->get($bulkName);
        $conditions = ['CustomerApiId = ?'];
        $parameters = [$customerApiId];

        if (!$includeRemoved && $definition->softDeleteColumn !== null) {
            $conditions[] = sprintf('%s = 0', $definition->softDeleteColumn);
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s ORDER BY %s ASC',
            $definition->table,
            implode(' AND ', $conditions),
            $definition->orderBy,
        );
        $rows = $this->db->fetchAll($sql, $parameters);

        if ($bulkName === 'users' && $attachUserPhotos) {
            return $this->attachUserPhotos($customerApiId, $rows);
        }

        return $rows;
    }

    public function push(
        string $bulkName,
        string $customerApiId,
        array $records,
    ): array {
        $definition = $this->catalog->get($bulkName);
        $customerApiId = trim($customerApiId);

        if ($bulkName === 'users') {
            return $this->pushUsers($definition, $customerApiId, $records);
        }

        return $this->pushGeneric($definition, $customerApiId, $records);
    }

    /**
     * @param array<mixed> $records
     * @return list<array<string, mixed>>
     */
    private function pushGeneric(
        DesktopBulkDefinition $definition,
        string $customerApiId,
        array $records,
    ): array {
        $results = [];

        foreach ($records as $record) {
            $primaryValue = $this->primaryValue($record, $definition->primaryKey);
            $result = [
                'id' => $primaryValue,
                'success' => false,
            ];

            if ($customerApiId === '' || $primaryValue === null || !is_array($record)) {
                $results[] = $result;
                continue;
            }

            try {
                if ($this->recordExists($definition, $primaryValue)) {
                    $updateData = $this->filterColumns(
                        $definition,
                        $record,
                        $customerApiId,
                        false,
                    );
                    unset($updateData[$definition->primaryKey]);

                    if ($updateData !== []) {
                        $this->db->update(
                            $definition->table,
                            $updateData,
                            sprintf('%s = ?', $definition->primaryKey),
                            [$primaryValue],
                        );
                    }
                } else {
                    $insertData = $this->filterColumns(
                        $definition,
                        $record,
                        $customerApiId,
                        true,
                    );
                    $this->db->insert($definition->table, $insertData);
                }

                $result['success'] = true;
            } catch (Throwable $exception) {
                error_log(sprintf(
                    'sync_push_error_%s: %s',
                    $definition->table,
                    $exception->getMessage(),
                ));
                $result['messageError'] = $exception->getMessage();
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * @param array<mixed> $records
     * @return list<array<string, mixed>>
     */
    private function pushUsers(
        DesktopBulkDefinition $definition,
        string $customerApiId,
        array $records,
    ): array {
        $results = [];

        foreach ($records as $record) {
            $id = $this->primaryValue($record, $definition->primaryKey);

            if ($id === null || $customerApiId === '' || !is_array($record)) {
                $results[] = [
                    'id' => $id,
                    'success' => false,
                ];
                continue;
            }

            $this->db->begin();

            try {
                $existing = $this->db->fetchOne(
                    'SELECT CustomerApiId FROM UsersDesktop WHERE Id = ? FOR UPDATE',
                    [$id],
                );

                if ($existing !== null && $existing['CustomerApiId'] !== $customerApiId) {
                    throw new \RuntimeException('Socio no accesible');
                }

                if ($existing !== null) {
                    $updateData = $this->filterColumns(
                        $definition,
                        $record,
                        $customerApiId,
                        false,
                    );
                    unset($updateData[$definition->primaryKey]);

                    $this->db->update(
                        $definition->table,
                        $updateData,
                        'Id = ? AND CustomerApiId = ?',
                        [
                            $id,
                            $customerApiId,
                        ],
                    );
                } else {
                    $insertData = $this->filterColumns(
                        $definition,
                        $record,
                        $customerApiId,
                        true,
                    );
                    $this->db->insert($definition->table, $insertData);
                }

                $this->db->commitTransaction();
                $results[] = [
                    'id' => $id,
                    'success' => true,
                ];
            } catch (Throwable) {
                $this->db->rollbackTransaction();
                $results[] = [
                    'id' => $id,
                    'success' => false,
                    'messageError' => 'No se pudo sincronizar el socio',
                ];
            }
        }

        return $results;
    }

    private function recordExists(
        DesktopBulkDefinition $definition,
        string $primaryValue,
    ): bool {
        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s = ? LIMIT 1',
            $definition->primaryKey,
            $definition->table,
            $definition->primaryKey,
        );

        return $this->db->fetchOne($sql, [$primaryValue]) !== null;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function filterColumns(
        DesktopBulkDefinition $definition,
        array $record,
        string $customerApiId,
        bool $forInsert,
    ): array {
        $filtered = [];

        foreach ($definition->columns as $column) {
            if ($column === 'CustomerApiId') {
                $filtered[$column] = $customerApiId;
                continue;
            }

            if (!array_key_exists($column, $record)) {
                continue;
            }

            $filtered[$column] = $this->normalizeColumnValue(
                $definition,
                $column,
                $record[$column],
            );
        }

        if (!isset($filtered['CustomerApiId'])) {
            $filtered['CustomerApiId'] = $customerApiId;
        }

        if (
            $forInsert
            && $definition->softDeleteColumn !== null
            && !array_key_exists($definition->softDeleteColumn, $filtered)
        ) {
            $filtered[$definition->softDeleteColumn] = 0;
        }

        return $filtered;
    }

    private function normalizeColumnValue(
        DesktopBulkDefinition $definition,
        string $column,
        mixed $value,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (!in_array($column, $definition->booleanColumns, true)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === 'true' || $normalized === '1') {
                return 1;
            }

            if ($normalized === 'false' || $normalized === '0') {
                return 0;
            }
        }

        return $value ? 1 : 0;
    }

    private function primaryValue(mixed $record, string $primaryKey): ?string
    {
        if (!is_array($record)) {
            return null;
        }

        $value = $record[$primaryKey] ?? null;

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function attachUserPhotos(string $customerApiId, array $rows): array
    {
        $userIds = array_values(array_unique(array_filter(array_column($rows, 'Id'))));
        $photosByUser = [];

        foreach (array_chunk($userIds, 500) as $userIdChunk) {
            $placeholders = implode(',', array_fill(0, count($userIdChunk), '?'));
            $sql = sprintf(
                'SELECT p.UserId, p.Version, p.UpdatedAt
                 FROM UserPhotosDesktop p
                 JOIN UsersDesktop u
                   ON u.Id = p.UserId
                  AND u.CustomerApiId = p.CustomerApiId
                 WHERE p.CustomerApiId = ?
                   AND COALESCE(u.Removed, 0) = 0
                   AND p.UserId IN (%s)',
                $placeholders,
            );
            $parameters = array_merge([$customerApiId], $userIdChunk);

            foreach ($this->db->fetchAll($sql, $parameters) as $photo) {
                $photosByUser[$photo['UserId']] = [
                    'version' => (int) $photo['Version'],
                    'updatedAt' => $photo['UpdatedAt'],
                ];
            }
        }

        foreach ($rows as &$row) {
            $row['photo'] = $photosByUser[$row['Id'] ?? ''] ?? null;
        }
        unset($row);

        return $rows;
    }
}
