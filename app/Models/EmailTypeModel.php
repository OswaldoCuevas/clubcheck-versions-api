<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

/**
 * Modelo para la tabla EmailTypes (catálogo de tipos de correo)
 */
class EmailTypeModel extends Model
{
    protected string $table = 'EmailTypes';
    protected string $primaryKey = 'Id';

    /**
     * Obtiene todos los tipos de correo activos
     */
    public function getActiveTypes(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE IsActive = 1 ORDER BY Id ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene todos los tipos de correo
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY Id ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Busca un tipo de correo por su código
     */
    public function findByCode(string $code): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE Code = ? AND IsActive = 1 LIMIT 1";
        return $this->db->fetchOne($sql, [strtoupper($code)]);
    }

    /**
     * Busca un tipo de correo por su ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE Id = ? LIMIT 1";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Obtiene los tipos que requieren código de verificación
     */
    public function getCodeRequiredTypes(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE RequiresCode = 1 AND IsActive = 1 ORDER BY Id ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Crea un nuevo tipo de correo
     */
    public function create(array $data): int
    {
        $insertData = [
            'Code' => strtoupper($data['code']),
            'Name' => $data['name'],
            'Description' => $data['description'] ?? null,
            'RequiresCode' => $data['requiresCode'] ?? false,
            'CodeExpirationMinutes' => $data['codeExpirationMinutes'] ?? null,
            'MaxAttempts' => $data['maxAttempts'] ?? null,
            'IsActive' => $data['isActive'] ?? true,
        ];

        return $this->db->insert($this->table, $insertData);
    }

    /**
     * Actualiza un tipo de correo
     */
    public function update(int $id, array $data): bool
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['Name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['Description'] = $data['description'];
        }
        if (isset($data['requiresCode'])) {
            $updateData['RequiresCode'] = $data['requiresCode'];
        }
        if (isset($data['codeExpirationMinutes'])) {
            $updateData['CodeExpirationMinutes'] = $data['codeExpirationMinutes'];
        }
        if (isset($data['maxAttempts'])) {
            $updateData['MaxAttempts'] = $data['maxAttempts'];
        }
        if (isset($data['isActive'])) {
            $updateData['IsActive'] = $data['isActive'];
        }

        if (empty($updateData)) {
            return false;
        }

        return $this->db->update($this->table, $updateData, 'Id = ?', [$id]);
    }

    /**
     * Desactiva un tipo de correo (soft delete)
     */
    public function deactivate(int $id): bool
    {
        return $this->db->update($this->table, ['IsActive' => 0], 'Id = ?', [$id]);
    }

    /**
     * Activa un tipo de correo
     */
    public function activate(int $id): bool
    {
        return $this->db->update($this->table, ['IsActive' => 1], 'Id = ?', [$id]);
    }
}
