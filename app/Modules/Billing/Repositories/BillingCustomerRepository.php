<?php

declare(strict_types=1);

namespace App\Modules\Billing\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Billing\Contracts\IBillingCustomerRepository;

final readonly class BillingCustomerRepository implements IBillingCustomerRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function customer(string $customerId): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT * FROM Customers WHERE Id = ? LIMIT 1',
            [$customerId],
        );

        return $row === null ? null : $this->map($row);
    }

    public function findByBillingId(string $billingId): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT * FROM Customers WHERE BillingId = ? LIMIT 1',
            [$billingId],
        );

        return $row === null ? null : $this->map($row);
    }

    public function findByEmail(string $email): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT * FROM Customers WHERE Email = ? LIMIT 1',
            [$email],
        );

        return $row === null ? null : $this->map($row);
    }

    public function billingId(string $customerId, ?string $default = null): ?string
    {
        return $this->customer($customerId)['billingId'] ?? $default;
    }

    public function accessCodeByBillingId(string $billingId): ?string
    {
        $row = $this->database->fetchOne(
            'SELECT CodeAccess FROM Customers WHERE BillingId = ? LIMIT 1',
            [$billingId],
        );

        return $row['CodeAccess'] ?? null;
    }

    private function map(array $row): array
    {
        return [
            'customerId' => $row['Id'],
            'name' => $row['Name'],
            'email' => $row['Email'],
            'phone' => $row['Phone'] ?? null,
            'billingId' => $row['BillingId'] ?? null,
            'token' => $row['Token'] ?? null,
            'deviceName' => $row['DeviceName'] ?? null,
            'isActive' => (bool) ($row['IsActive'] ?? true),
            'codeAccess' => $row['CodeAccess'] ?? null,
            'customerJwt' => $row['TokenJwt'] ?? null,
        ];
    }
}
