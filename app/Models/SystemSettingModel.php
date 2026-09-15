<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class SystemSettingModel extends Model
{
    protected function initialize(): void
    {
        // Nada adicional requerido.
    }

    public function hasTable(): bool
    {
        try {
            return $this->db->fetchOne("SHOW TABLES LIKE 'SystemSettings'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function get(string $key, $default = null)
    {
        if (!$this->hasTable()) {
            return $default;
        }

        $row = $this->db->fetchOne(
            'SELECT SettingValue FROM SystemSettings WHERE SettingKey = ? LIMIT 1',
            [$key]
        );

        return $row['SettingValue'] ?? $default;
    }

    public function set(string $key, $value, ?string $description = null): void
    {
        if (!$this->hasTable()) {
            throw new \RuntimeException('Ejecuta primero la migracion 013_create_system_settings.sql');
        }

        $this->db->execute_query(
            'INSERT INTO SystemSettings (SettingKey, SettingValue, Description)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue), Description = COALESCE(VALUES(Description), Description)',
            [$key, (string)$value, $description]
        );
    }
}
