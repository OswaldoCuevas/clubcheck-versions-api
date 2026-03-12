<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

/**
 * WhatsApp Configuration Model
 * 
 * Manages WhatsApp Business API configuration per customer.
 * Each customer can have only ONE WhatsApp configuration.
 */
class WhatsAppConfigurationModel extends Model
{
    private string $table = 'WhatsAppConfigurations';

    protected function initialize()
    {
        // No additional initialization required.
    }

    // -------------------------------------------------------------------------
    // UUID Generation
    // -------------------------------------------------------------------------

    /**
     * Generates a UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Gets current timestamp
     */
    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /**
     * Find a configuration by ID
     */
    public function findById(string $id): ?array
    {
        $result = $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE Id = ? LIMIT 1",
            [$id]
        );

        return $result ?: null;
    }

    /**
     * Find the configuration for a customer (single config per customer)
     */
    public function findByCustomerId(string $customerId): ?array
    {
        $result = $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE CustomerId = ? AND IsActive = 1 LIMIT 1",
            [$customerId]
        );

        return $result ?: null;
    }

    /**
     * Find configuration by phone number ID
     */
    public function findByPhoneNumberId(string $phoneNumberId): ?array
    {
        $result = $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE PhoneNumberId = ? LIMIT 1",
            [$phoneNumberId]
        );

        return $result ?: null;
    }

    /**
     * Check if a customer has a WhatsApp configuration
     */
    public function customerHasConfiguration(string $customerId): bool
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} WHERE CustomerId = ? AND IsActive = 1",
            [$customerId]
        );

        return (int) ($result['total'] ?? 0) > 0;
    }

    // -------------------------------------------------------------------------
    // CRUD Operations
    // -------------------------------------------------------------------------

    /**
     * Create a new WhatsApp configuration
     * 
     * @param array $data Configuration data:
     *   - CustomerId (required)
     *   - PhoneNumber (required)
     *   - PhoneNumberId (required)
     *   - BusinessName (required)
     *   - AccessToken (optional)
     *   - BusinessAddress (optional)
     *   - BusinessDescription (optional)
     *   - BusinessEmail (optional)
     *   - BusinessVertical (optional)
     *   - BusinessWebsites (optional, array)
     *   - ProfilePictureUrl (optional)
     *   - CreatedBy (optional)
     * 
     * @return array ['success' => bool, 'id' => string|null, 'error' => string|null]
     */
    public function create(array $data): array
    {
        // Validate required fields
        $required = ['CustomerId', 'PhoneNumber', 'PhoneNumberId', 'BusinessName'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => "Campo requerido faltante: {$field}"
                ];
            }
        }

        $id = $this->generateUuid();
        $now = $this->now();

        // Prepare websites JSON
        $websites = null;
        if (isset($data['BusinessWebsites'])) {
            $websites = is_array($data['BusinessWebsites']) 
                ? json_encode($data['BusinessWebsites']) 
                : $data['BusinessWebsites'];
        }

        try {
            $this->db->execute_query(
                "INSERT INTO {$this->table} (
                    Id, CustomerId, PhoneNumber, PhoneNumberId, AccessToken,
                    BusinessName, BusinessAddress, BusinessDescription, BusinessEmail,
                    BusinessVertical, BusinessWebsites, ProfilePictureUrl,
                    IsActive, CreatedAt, UpdatedAt, CreatedBy
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)",
                [
                    $id,
                    $data['CustomerId'],
                    $data['PhoneNumber'],
                    $data['PhoneNumberId'],
                    $data['AccessToken'] ?? null,
                    $data['BusinessName'],
                    $data['BusinessAddress'] ?? null,
                    $data['BusinessDescription'] ?? null,
                    $data['BusinessEmail'] ?? null,
                    $data['BusinessVertical'] ?? null,
                    $websites,
                    $data['ProfilePictureUrl'] ?? null,
                    $now,
                    $now,
                    $data['CreatedBy'] ?? null
                ]
            );

            return [
                'success' => true,
                'id' => $id,
                'error' => null
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Error al crear configuración: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing WhatsApp configuration
     * 
     * @param string $id Configuration ID
     * @param array $data Fields to update
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function update(string $id, array $data): array
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'error' => 'Configuración no encontrada'
            ];
        }

        $fields = [];
        $params = [];

        // Allowed fields to update
        $allowedFields = [
            'PhoneNumber', 'PhoneNumberId', 'AccessToken',
            'BusinessName', 'BusinessAddress', 'BusinessDescription',
            'BusinessEmail', 'BusinessVertical', 'BusinessWebsites',
            'ProfilePictureUrl', 'IsActive', 'UpdatedBy'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                
                // Handle BusinessWebsites as JSON
                if ($field === 'BusinessWebsites' && is_array($value)) {
                    $value = json_encode($value);
                }
                
                // Handle boolean fields
                if ($field === 'IsActive') {
                    $value = $value ? 1 : 0;
                }
                
                $fields[] = "`{$field}` = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return [
                'success' => false,
                'error' => 'No hay campos para actualizar'
            ];
        }

        // Add UpdatedAt
        $fields[] = 'UpdatedAt = ?';
        $params[] = $this->now();

        // Add ID for WHERE clause
        $params[] = $id;

        try {
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE Id = ?";
            $this->db->execute_query($sql, $params);

            return [
                'success' => true,
                'error' => null
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Error al actualizar configuración: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create or update a WhatsApp configuration (upsert by CustomerId)
     * 
     * Since each customer can only have ONE configuration, the upsert
     * matches on CustomerId alone. If a config exists, it updates it.
     * Otherwise, it creates a new one.
     * 
     * @param array $data Configuration data
     * @return array ['success' => bool, 'id' => string|null, 'created' => bool, 'error' => string|null]
     */
    public function upsert(array $data): array
    {
        if (empty($data['CustomerId'])) {
            return [
                'success' => false,
                'id' => null,
                'created' => false,
                'error' => 'CustomerId es requerido'
            ];
        }

        $existing = $this->findByCustomerId($data['CustomerId']);

        if ($existing) {
            // Update existing
            $result = $this->update($existing['Id'], $data);
            return [
                'success' => $result['success'],
                'id' => $result['success'] ? $existing['Id'] : null,
                'created' => false,
                'error' => $result['error']
            ];
        } else {
            // Create new
            $result = $this->create($data);
            return [
                'success' => $result['success'],
                'id' => $result['id'],
                'created' => true,
                'error' => $result['error']
            ];
        }
    }

    /**
     * Update profile picture URL after sync with WhatsApp API
     */
    public function updateProfilePicture(string $id, ?string $url, ?string $updatedBy = null): array
    {
        return $this->update($id, [
            'ProfilePictureUrl' => $url,
            'UpdatedBy' => $updatedBy
        ]);
    }

    // -------------------------------------------------------------------------
    // Statistics
    // -------------------------------------------------------------------------

    /**
     * Get all active configurations with customer info
     */
    public function getAllActiveWithCustomerInfo(): array
    {
        return $this->db->fetchAll(
            "SELECT wc.*, c.Name AS CustomerName, c.Email AS CustomerEmail
             FROM {$this->table} wc
             INNER JOIN Customers c ON c.Id = wc.CustomerId
             WHERE wc.IsActive = 1
             ORDER BY c.Name ASC"
        );
    }

    /**
     * Get all configurations (including inactive) with customer info
     */
    public function getAllWithCustomerInfo(): array
    {
        return $this->db->fetchAll(
            "SELECT wc.*, c.Name AS CustomerName, c.Email AS CustomerEmail
             FROM {$this->table} wc
             INNER JOIN Customers c ON c.Id = wc.CustomerId
             ORDER BY wc.CreatedAt DESC"
        );
    }

    /**
     * Delete a WhatsApp configuration
     */
    public function delete(string $id): array
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return [
                'success' => false,
                'error' => 'Configuración no encontrada'
            ];
        }

        try {
            $result = $this->db->delete($this->table, 'Id = ?', [$id], 1);

            if (!$result) {
                return [
                    'success' => false,
                    'error' => 'No se pudo eliminar la configuración'
                ];
            }

            return [
                'success' => true,
                'error' => null
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Error al eliminar configuración: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Soft delete (deactivate) a WhatsApp configuration
     */
    public function deactivate(string $id, ?string $updatedBy = null): array
    {
        return $this->update($id, [
            'IsActive' => false,
            'UpdatedBy' => $updatedBy
        ]);
    }

    /**
     * Activate a WhatsApp configuration
     */
    public function activate(string $id, ?string $updatedBy = null): array
    {
        return $this->update($id, [
            'IsActive' => true,
            'UpdatedBy' => $updatedBy
        ]);
    }
}
