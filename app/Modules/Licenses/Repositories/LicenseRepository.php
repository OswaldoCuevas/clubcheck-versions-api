<?php

declare(strict_types=1);

namespace App\Modules\Licenses\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Licenses\Contracts\ILicenseRepository;

final readonly class LicenseRepository implements ILicenseRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function all(): array
    {
        return array_map($this->map(...), $this->db->fetchAll('SELECT * FROM LicenseLogs ORDER BY IssuedAt DESC'));
    }

    public function log(array $d): int
    {
        return $this->db->insert('LicenseLogs', [
            'CustomerId' => $d['CustomerId'] ?? null,
            'BillingId' => $d['BillingId'] ?? null,
            'CustomerName' => $d['CustomerName'] ?? '',
            'CustomerEmail' => $d['CustomerEmail'] ?? null,
            'PlanLookupKey' => $d['PlanLookupKey'] ?? '',
            'PlanName' => $d['PlanName'] ?? '',
            'IsPermanent' => !empty($d['IsPermanent']) ? 1 : 0,
            'ExpiresAt' => $d['ExpiresAt'] ?? null,
            'MachineToken' => $d['MachineToken'] ?? null,
            'LicenseToken' => $d['LicenseToken'],
            'CreatedBy' => in_array($d['CreatedBy'] ?? '', ['admin', 'customer'], true)
                ? $d['CreatedBy']
                : 'customer',
            'AdminUsername' => $d['AdminUsername'] ?? null,
            'IssuedAt' => date('Y-m-d H:i:s'),
        ]);
    }

    public function customer(string $id): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM Customers WHERE Id=?', [$id]);

        return $row ? [
            'customerId' => $row['Id'],
            'billingId' => $row['BillingId'] ?? null,
            'name' => $row['Name'],
            'email' => $row['Email'],
            'token' => $row['Token'],
        ] : null;
    }

    public function customerTokenRow(string $id): ?array
    {
        return $this->db->fetchOne('SELECT Id,Token,TokenJwt FROM Customers WHERE Id=? LIMIT 1', [$id]);
    }

    public function storeCustomerJwt(string $id, string $jwt): void
    {
        $this->db->update(
            'Customers',
            [
                'TokenJwt' => $jwt,
                'TokenJwtCreatedAt' => date('Y-m-d H:i:s'),
                'TokenJwtExpiresAt' => null,
            ],
            'Id=?',
            [$id],
        );
    }

    private function map(array $r): array
    {
        return [
            'id' => (int)$r['Id'],
            'customerId' => $r['CustomerId'],
            'billingId' => $r['BillingId'],
            'customerName' => $r['CustomerName'],
            'customerEmail' => $r['CustomerEmail'],
            'planLookupKey' => $r['PlanLookupKey'],
            'planName' => $r['PlanName'],
            'isPermanent' => (bool)$r['IsPermanent'],
            'expiresAt' => $r['ExpiresAt'],
            'machineToken' => $r['MachineToken'],
            'licenseToken' => $r['LicenseToken'],
            'createdBy' => $r['CreatedBy'],
            'adminUsername' => $r['AdminUsername'],
            'issuedAt' => $r['IssuedAt'],
        ];
    }
}
