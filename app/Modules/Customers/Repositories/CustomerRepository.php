<?php

declare(strict_types=1);

namespace App\Modules\Customers\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Customers\Contracts\ICustomerRepository;
use RuntimeException;

final readonly class CustomerRepository implements ICustomerRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function getAll(): array
    {
        return array_map($this->map(...), $this->db->fetchAll('SELECT * FROM Customers ORDER BY Name ASC, Id ASC'));
    }
    public function find(string $customerId): ?array
    {
        $id = trim($customerId);
        if ($id === '') {
            return null;
        }

        $row = $this->raw($id);

        return $row ? $this->map($row) : null;
    }

    public function save(string $customerId, array $attributes): array
    {
        $id = trim($customerId);
        if ($id === '') {
            throw new \InvalidArgumentException('customerId is required');
        }

        $existing = $this->raw($id);
        $now = date('Y-m-d H:i:s');

        if ($existing === null) {
            $key = $this->newAccessKey();
            $code = $this->slug((string)($attributes['codeAccess'] ?? $attributes['name'] ?? ''));
            $this->assertUnique(null, $code, $id);
            $this->db->insert('Customers', [
                'Id' => $id,
                'BillingId' => $this->nullable($attributes['billingId'] ?? null),
                'PlanCode' => $this->nullable($attributes['planCode'] ?? null),
                'Name' => $attributes['name'] ?? null,
                'CodeAccess' => $code ?: null,
                'Email' => $attributes['email'] ?? null,
                'Phone' => $attributes['phone'] ?? null,
                'DeviceName' => $attributes['deviceName'] ?? null,
                'Token' => $this->nullable($attributes['token'] ?? null),
                'AccessKeyHash' => $this->hashKey($key),
                'IsActive' => array_key_exists('isActive', $attributes)
                    ? ($attributes['isActive'] ? 1 : 0)
                    : 1,
                'WaitingForToken' => 0,
                'WaitingSince' => null,
                'TokenUpdatedAt' => !empty($attributes['token']) ? $now : null,
                'LastSeen' => null,
                'Metadata' => null,
                'CreatedAt' => $now,
                'UpdatedAt' => $now,
            ]);

            return $this->find($id) ?? throw new RuntimeException('customer_not_found_after_insert');
        }

        $update = [];
        $map = [
            'name' => 'Name',
            'billingId' => 'BillingId',
            'planCode' => 'PlanCode',
            'email' => 'Email',
            'phone' => 'Phone',
            'deviceName' => 'DeviceName',
            'token' => 'Token',
        ];

        foreach ($map as $from => $to) {
            if (array_key_exists($from, $attributes)) {
                $update[$to] = in_array($from, ['billingId', 'planCode', 'token'], true)
                    ? $this->nullable($attributes[$from])
                    : $attributes[$from];
            }
        }

        if (array_key_exists('codeAccess', $attributes)) {
            $code = $this->slug((string)$attributes['codeAccess']);
            $update['CodeAccess'] = $code ?: null;
        } else {
            $code = $existing['CodeAccess'] ?? null;
        }
        $this->assertUnique($attributes['email'] ?? ($existing['Email'] ?? null), $code, $id);
        if (array_key_exists('token', $attributes)) {
            $update['TokenUpdatedAt'] = empty($attributes['token']) ? null : $now;
        }

        if (array_key_exists('isActive', $attributes)) {
            $update['IsActive'] = $attributes['isActive'] ? 1 : 0;
        }

        if ($update !== []) {
            $update['UpdatedAt'] = $now;
            $this->db->update('Customers', $update, 'Id = ?', [$id]);
        }

        return $this->find($id) ?? throw new RuntimeException('customer_not_found_after_update');
    }

    public function regenerateAccessKey(string $customerId): ?array
    {
        $id = trim($customerId);
        if ($this->raw($id) === null) {
            return null;
        }

        do {
            $key = $this->newAccessKey();
            $hash = $this->hashKey($key);
        } while ($this->db->fetchOne('SELECT Id FROM Customers WHERE AccessKeyHash = ?', [$hash]));
        $this->db->update(
            'Customers',
            [
                'AccessKeyHash' => $hash,
                'UpdatedAt' => date('Y-m-d H:i:s'),
            ],
            'Id = ?',
            [$id],
        );

        return [
            'customerId' => $id,
            'accessKey' => $key,
            'customer' => $this->find($id),
        ];
    }
    public function delete(string $customerId): bool
    {
        $id = trim($customerId);
        return $id !== '' && $this->db->delete('Customers', 'Id = ?', [$id], 1);
    }
    private function raw(string $id): ?array
    {
        return $id === '' ? null : $this->db->fetchOne('SELECT * FROM Customers WHERE Id = ?', [$id]);
    }
    private function assertUnique(?string $email, ?string $code, string $ignore): void
    {
        if ($email) {
            $r = $this->db->fetchOne(
                'SELECT Id FROM Customers WHERE Email = ? AND Id <> ? LIMIT 1',
                [$email, $ignore],
            );
            if ($r) {
                throw new RuntimeException('email_already_registered');
            }
        }

        if ($code) {
            $r = $this->db->fetchOne(
                'SELECT Id FROM Customers WHERE CodeAccess = ? AND Id <> ? LIMIT 1',
                [$code, $ignore],
            );
            if ($r) {
                throw new RuntimeException('code_access_already_registered');
            }
        }
    }
    private function slug(string $value): string
    {
        $v = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $v = strtolower($v === false ? $value : $v);
        return trim(preg_replace('/[^a-z0-9]+/', '_', $v) ?? '', '_');
    }
    private function nullable(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
    private function newAccessKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $parts = [];
        for ($i = 0; $i < 4; $i++) {
            $part = '';
            for ($j = 0; $j < 4; $j++) {
                $part .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $parts[] = $part;
        }

        return implode('-', $parts);
    }
    private function hashKey(string $key): string
    {
        $secret = getenv('ACCESS_KEY_SECRET') ?: ($_ENV['ACCESS_KEY_SECRET'] ?? $_SERVER['ACCESS_KEY_SECRET'] ?? null);
        if (!$secret) {
            throw new RuntimeException('access_key_secret_missing');
        }

        return hash_hmac('sha512', $key, (string)$secret);
    }
    private function map(array $r): array
    {
        return [
            'customerId' => $r['Id'],
            'billingId' => $r['BillingId'] ?? null,
            'planCode' => $r['PlanCode'] ?? null,
            'name' => $r['Name'],
            'codeAccess' => $r['CodeAccess'] ?? null,
            'email' => $r['Email'],
            'phone' => $r['Phone'],
            'deviceName' => $r['DeviceName'],
            'clientVersion' => $r['ClientVersion'] ?? null,
            'clientVersionUpdatedAt' => $this->timestamp($r['ClientVersionUpdatedAt'] ?? null),
            'token' => $r['Token'],
            'isActive' => (bool)($r['IsActive'] ?? 1),
            'waitingForToken' => (bool)($r['WaitingForToken'] ?? 0),
            'waitingSince' => $this->timestamp($r['WaitingSince'] ?? null),
            'tokenUpdatedAt' => $this->timestamp($r['TokenUpdatedAt'] ?? null),
            'lastSeen' => $this->timestamp($r['LastSeen'] ?? null),
            'metadata' => !empty($r['Metadata'])
                ? (json_decode($r['Metadata'], true) ?: null)
                : null,
            'createdAt' => $this->timestamp($r['CreatedAt'] ?? null),
            'updatedAt' => $this->timestamp($r['UpdatedAt'] ?? null),
        ];
    }
    private function timestamp(?string $value): ?int
    {
        return $value ? strtotime($value) ?: null : null;
    }
}
