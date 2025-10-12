<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

abstract class BaseDesktopSyncModel extends Model
{
    protected string $table;
    protected string $primaryKey;
    protected array $columns = [];
    protected array $nullableColumns = [];
    protected array $booleanColumns = [];
    protected bool $autoIncrement = true;
    protected ?string $orderBy = null;

    protected function initialize()
    {
        // No se requiere inicialización adicional.
    }

    public function pull(string $customerApiId): array
    {
        $customerApiId = trim($customerApiId);

        if ($customerApiId === '') {
            return [];
        }

        $orderColumn = $this->orderBy ?? $this->primaryKey ?? 'Uuid';
        $sql = sprintf('SELECT * FROM %s WHERE CustomerApiId = ? ORDER BY %s ASC', $this->table, $orderColumn);

        return $this->db->fetchAll($sql, [$customerApiId]);
    }

    public function push(string $customerApiId, array $records): array
    {
        $customerApiId = trim($customerApiId);
        $results = [];

        foreach ($records as $record) {
            $uuid = $this->normalizeUuid($record['Uuid'] ?? null);
            $result = [
                'uuid' => $uuid,
                'success' => false,
            ];

            if ($customerApiId === '' || $uuid === null) {
                $results[] = $result;
                continue;
            }

            try {
                if ($this->recordExists($uuid)) {
                    $updateData = $this->prepareUpdate($record, $customerApiId);

                    if (!empty($updateData)) {
                        $this->db->update($this->table, $updateData, 'Uuid = ?', [$uuid]);
                    }
                } else {
                    $insertData = $this->prepareInsert($record, $customerApiId);
                    $this->db->insert($this->table, $insertData);
                }

                $result['success'] = true;
            } catch (\Throwable $e) {
                $this->log(sprintf('sync_push_error_%s: %s', $this->table, $e->getMessage()), 'error');
            }

            $results[] = $result;
        }

        return $results;
    }

    protected function recordExists(string $uuid): bool
    {
        $row = $this->db->fetchOne(
            sprintf('SELECT %s FROM %s WHERE Uuid = ? LIMIT 1', $this->primaryKey, $this->table),
            [$uuid]
        );

        return $row !== null;
    }

    protected function prepareInsert(array $record, string $customerApiId): array
    {
        $data = $this->filterColumns($record, $customerApiId, true);

        if (!isset($data['Uuid']) && isset($record['Uuid'])) {
            $data['Uuid'] = $this->normalizeUuid($record['Uuid']);
        }

        if ($this->autoIncrement
            && isset($data[$this->primaryKey])
            && ($data[$this->primaryKey] === null || $data[$this->primaryKey] === '')) {
            unset($data[$this->primaryKey]);
        }

        return $data;
    }

    protected function prepareUpdate(array $record, string $customerApiId): array
    {
        $data = $this->filterColumns($record, $customerApiId, false);

        unset($data[$this->primaryKey]);
        unset($data['Uuid']);

        return $data;
    }

    protected function filterColumns(array $record, string $customerApiId, bool $forInsert): array
    {
        $filtered = [];

        foreach ($this->columns as $column) {
            if ($column === 'CustomerApiId') {
                $filtered[$column] = $customerApiId;
                continue;
            }

            if (!array_key_exists($column, $record)) {
                continue;
            }

            $value = $this->normalizeColumnValue($column, $record[$column]);

            if ($value === null && !$this->isNullable($column)) {
                if ($forInsert && $column === $this->primaryKey && $this->autoIncrement) {
                    continue;
                }
            }

            $filtered[$column] = $value;
        }

        if (!isset($filtered['CustomerApiId'])) {
            $filtered['CustomerApiId'] = $customerApiId;
        }

        return $filtered;
    }

    protected function normalizeColumnValue(string $column, $value)
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (in_array($column, $this->booleanColumns, true)) {
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

        return $value;
    }

    protected function normalizeUuid($uuid): ?string
    {
        if ($uuid === null) {
            return null;
        }

        $uuid = trim((string) $uuid);

        return $uuid === '' ? null : $uuid;
    }

    protected function isNullable(string $column): bool
    {
        return in_array($column, $this->nullableColumns, true);
    }
}
