<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/../../utils/GlobalFunctions.php';

use Core\Model;

class CustomerPushTokenModel extends Model
{
    public function customersWithDeviceCounts(): array
    {
        return $this->db->fetchAll(
            'SELECT c.Id, c.Name, COUNT(p.Id) AS DeviceCount
             FROM Customers c
             LEFT JOIN CustomerPushTokens p ON p.CustomerId = c.Id
             WHERE c.IsActive = 1
             GROUP BY c.Id, c.Name
             ORDER BY c.Name, c.Id'
        );
    }

    public function register(string $customerId, string $token, ?string $platform): void
    {
        $this->db->execute_query(
            'INSERT INTO CustomerPushTokens (Id, CustomerId, Token, TokenHash, Platform)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE CustomerId = VALUES(CustomerId), Token = VALUES(Token),
                 Platform = VALUES(Platform), UpdatedAt = CURRENT_TIMESTAMP',
            [\GlobalFunctions::generateUuid(), $customerId, $token, hash('sha256', $token), $platform]
        );
    }

    public function remove(string $customerId, string $token): bool
    {
        return $this->db->delete('CustomerPushTokens', 'CustomerId = ? AND TokenHash = ?',
            [$customerId, hash('sha256', $token)]);
    }

    public function removeInvalid(string $id, string $token): void
    {
        $this->db->delete('CustomerPushTokens', 'Id = ? AND TokenHash = ?',
            [$id, hash('sha256', $token)]);
    }

    public function forCustomer(string $customerId): array
    {
        return $this->db->fetchAll(
            'SELECT Id, Token FROM CustomerPushTokens WHERE CustomerId = ? ORDER BY Id',
            [$customerId]
        );
    }
}
