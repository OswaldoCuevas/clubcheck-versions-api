<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;

final readonly class PushTokenRepository implements \App\Modules\PushNotifications\Contracts\IPushTokenRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function customersWithDeviceCounts(): array
    {
        return $this->database->fetchAll(
            'SELECT c.Id, c.Name, COUNT(p.Id) AS DeviceCount
             FROM Customers c
             LEFT JOIN CustomerPushTokens p ON p.CustomerId = c.Id
             WHERE c.IsActive = 1
             GROUP BY c.Id, c.Name
             ORDER BY c.Name, c.Id',
        );
    }

    public function customerExists(string $customerId): bool
    {
        return $this->database->fetchOne(
            'SELECT Id FROM Customers WHERE Id = ? LIMIT 1',
            [$customerId],
        ) !== null;
    }

    public function register(string $customerId, string $token, ?string $platform): void
    {
        $this->database->execute(
            'INSERT INTO CustomerPushTokens (Id, CustomerId, Token, TokenHash, Platform)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE CustomerId = VALUES(CustomerId), Token = VALUES(Token),
                 Platform = VALUES(Platform), UpdatedAt = CURRENT_TIMESTAMP',
            [self::uuid(), $customerId, $token, hash('sha256', $token), $platform],
        );
    }

    public function remove(string $customerId, string $token): bool
    {
        return $this->database->delete(
            'CustomerPushTokens',
            'CustomerId = ? AND TokenHash = ?',
            [$customerId, hash('sha256', $token)],
        );
    }

    public function removeInvalid(string $id, string $token): void
    {
        $this->database->delete(
            'CustomerPushTokens',
            'Id = ? AND TokenHash = ?',
            [$id, hash('sha256', $token)],
        );
    }

    public function forCustomer(string $customerId, ?string $platform = null): array
    {
        if ($platform !== null) {
            return $this->database->fetchAll(
                'SELECT Id, Token FROM CustomerPushTokens
                 WHERE CustomerId = ? AND Platform = ? ORDER BY Id',
                [$customerId, $platform],
            );
        }

        return $this->database->fetchAll(
            'SELECT Id, Token FROM CustomerPushTokens WHERE CustomerId = ? ORDER BY Id',
            [$customerId],
        );
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}
