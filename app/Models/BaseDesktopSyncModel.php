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
    protected ?string $softDeleteColumn = null;

    protected function initialize()
    {
        // No se requiere inicialización adicional.
    }

    public function pull(string $customerApiId, bool $includeRemoved = false): array
    {
        $customerApiId = trim($customerApiId);

        if ($customerApiId === '') {
            return [];
        }

        $orderColumn = $this->orderBy ?? $this->primaryKey;

        $conditions = ['CustomerApiId = ?'];
        $params = [$customerApiId];

        if ($this->shouldFilterSoftDeleted($includeRemoved)) {
            $conditions[] = sprintf('%s = 0', $this->softDeleteColumn);
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s ORDER BY %s ASC',
            $this->table,
            implode(' AND ', $conditions),
            $orderColumn
        );

    return $this->db->fetchAll($sql, $params);
    }

    public function push(string $customerApiId, array $records): array
    {
        $customerApiId = trim($customerApiId);
        $results = [];

        foreach ($records as $record) {
            $primaryValue = $this->normalizePrimaryKey($record[$this->primaryKey] ?? null);
            $result = [
                'id' => $primaryValue,
                'success' => false,
            ];

            if ($customerApiId === '' || $primaryValue === null) {
                $results[] = $result;
                continue;
            }

            try {
                if ($this->recordExists($primaryValue)) {
                    $updateData = $this->prepareUpdate($record, $customerApiId);

                    if (!empty($updateData)) {
                        $this->db->update($this->table, $updateData, sprintf('%s = ?', $this->primaryKey), [$primaryValue]);
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

    protected function recordExists(string $primaryValue): bool
    {
        $row = $this->db->fetchOne(
            sprintf('SELECT %s FROM %s WHERE %s = ? LIMIT 1', $this->primaryKey, $this->table, $this->primaryKey),
            [$primaryValue]
        );

        return $row !== null;
    }

    protected function prepareInsert(array $record, string $customerApiId): array
    {
        $data = $this->filterColumns($record, $customerApiId, true);

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

        if (
            $forInsert
            && $this->softDeleteColumn !== null
            && in_array($this->softDeleteColumn, $this->columns, true)
            && !array_key_exists($this->softDeleteColumn, $filtered)
        ) {
            $filtered[$this->softDeleteColumn] = 0;
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

    protected function normalizePrimaryKey($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    protected function isNullable(string $column): bool
    {
        return in_array($column, $this->nullableColumns, true);
    }

    protected function shouldFilterSoftDeleted(bool $includeRemoved): bool
    {
        if ($this->softDeleteColumn === null) {
            return false;
        }

        if ($includeRemoved) {
            return false;
        }

        return in_array($this->softDeleteColumn, $this->columns, true);
    }
}
