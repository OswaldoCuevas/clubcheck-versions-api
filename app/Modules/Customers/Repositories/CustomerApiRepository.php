<?php

declare(strict_types=1);

namespace App\Modules\Customers\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Customers\Contracts\ICustomerApiRepository;
use InvalidArgumentException;
use RuntimeException;

final readonly class CustomerApiRepository implements ICustomerApiRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function find(string $customerId): ?array
    {
        $row = $this->raw(trim($customerId));

        return $row !== null
            ? $this->map($row)
            : null;
    }

    public function save(string $customerId, array $attributes): array
    {
        $customerId = trim($customerId);

        if ($customerId === '') {
            throw new InvalidArgumentException('customerId is required');
        }

        $existing = $this->raw($customerId);

        if ($existing === null) {
            return $this->createCustomer($customerId, $attributes);
        }

        $update = [];
        $columnMap = [
            'name' => 'Name',
            'billingId' => 'BillingId',
            'planCode' => 'PlanCode',
            'email' => 'Email',
            'phone' => 'Phone',
            'deviceName' => 'DeviceName',
            'token' => 'Token',
        ];

        foreach ($columnMap as $attribute => $column) {
            if (!array_key_exists($attribute, $attributes)) {
                continue;
            }

            $value = $attributes[$attribute];
            $update[$column] = in_array(
                $attribute,
                [
                    'billingId',
                    'planCode',
                    'token',
                ],
                true,
            ) ? $this->nullable($value) : $value;
        }

        if (array_key_exists('codeAccess', $attributes)) {
            $codeAccess = $attributes['codeAccess'] !== null
                ? $this->slug((string) $attributes['codeAccess'])
                : null;
            $this->assertCodeAvailable($codeAccess, $customerId);
            $update['CodeAccess'] = $this->nullable($codeAccess);
        } elseif (
            array_key_exists('name', $attributes)
            && !empty($attributes['name'])
            && empty($existing['CodeAccess'])
        ) {
            $update['CodeAccess'] = $this->uniqueCode(
                (string) $attributes['name'],
                $customerId,
            );
        }

        if (array_key_exists('email', $attributes)) {
            $this->assertEmailAvailable(
                $attributes['email'],
                $customerId,
            );
        }

        if (array_key_exists('token', $attributes)) {
            $update['TokenUpdatedAt'] = empty($attributes['token'])
                ? null
                : $this->now();
        }

        if (array_key_exists('isActive', $attributes)) {
            $update['IsActive'] = $attributes['isActive'] ? 1 : 0;
        }

        if ($update !== []) {
            $update['UpdatedAt'] = $this->now();
            $this->db->update(
                'Customers',
                $update,
                'Id = ?',
                [$customerId],
            );
        }

        return $this->find($customerId)
            ?? throw new RuntimeException('customer_not_found_after_update');
    }

    public function register(array $data): array
    {
        $specifiedId = trim((string) ($data['customerId'] ?? ''));

        if ($specifiedId !== '') {
            $existing = $this->find($specifiedId);

            if ($existing !== null) {
                return [
                    'found' => true,
                    'customer' => $existing,
                ];
            }
        }

        $customerId = $specifiedId !== ''
            ? $specifiedId
            : $this->newCustomerId();
        $email = $this->nullable($data['email'] ?? null);
        $this->assertEmailAvailable($email, $specifiedId ?: null);

        do {
            $accessKey = $this->newAccessKey();
            $accessKeyHash = $this->hashAccessKey($accessKey);
        } while ($this->accessKeyHashExists($accessKeyHash));

        $now = $this->now();
        $machineToken = $this->nullable($data['token'] ?? null);
        $codeAccess = $this->uniqueCode(
            (string) $data['name'],
            $customerId,
        );

        $this->db->insert(
            'Customers',
            [
                'Id' => $customerId,
                'BillingId' => $this->nullable($data['billingId'] ?? null),
                'PlanCode' => $this->nullable($data['planCode'] ?? null),
                'Name' => $data['name'],
                'CodeAccess' => $codeAccess,
                'Email' => $email,
                'Phone' => $this->nullable($data['phone'] ?? null),
                'DeviceName' => $this->nullable($data['deviceName'] ?? null),
                'Token' => $machineToken,
                'AccessKeyHash' => $accessKeyHash,
                'IsActive' => array_key_exists('isActive', $data)
                    ? ($data['isActive'] ? 1 : 0)
                    : 1,
                'WaitingForToken' => 0,
                'WaitingSince' => null,
                'TokenUpdatedAt' => $machineToken !== null ? $now : null,
                'LastSeen' => null,
                'Metadata' => null,
                'CreatedAt' => $now,
                'UpdatedAt' => $now,
            ],
        );

        $this->insertConsent(
            'CustomerPrivacyConsents',
            $customerId,
            $data['privacyAcceptance'],
        );
        $this->insertConsent(
            'CustomerTermsAndConditionsConsents',
            $customerId,
            $data['termsAndConditionsAcceptance'],
        );

        return [
            'found' => false,
            'customer' => $this->find($customerId),
            'accessKey' => $accessKey,
        ];
    }

    public function isEmailAvailable(
        ?string $email,
        ?string $ignoreCustomerId = null,
    ): bool
    {
        if ($email === null || $email === '') {
            return true;
        }

        $row = $this->db->fetchOne(
            'SELECT Id FROM Customers WHERE Email = ? LIMIT 1',
            [$email],
        );

        if ($row === null) {
            return true;
        }

        return $ignoreCustomerId !== null
            && trim($ignoreCustomerId) !== ''
            && $row['Id'] === trim($ignoreCustomerId);
    }

    public function login(
        string $email,
        string $accessKey,
        string $deviceName,
        ?string $ipAddress,
        string $newToken,
    ): array
    {
        $emailKey = mb_strtolower(trim($email));
        $since = date('Y-m-d H:i:s', time() - 3600);
        $attempts = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM CustomerLoginAttempts '
                . 'WHERE Email = ? AND WasSuccessful = 0 AND CreatedAt >= ?',
            [
                $emailKey,
                $since,
            ],
        );

        if ((int) ($attempts['total'] ?? 0) >= 5) {
            throw new RuntimeException('too_many_attempts');
        }

        $row = $this->db->fetchOne(
            'SELECT * FROM Customers WHERE Email = ?',
            [$email],
        );

        if ($row === null || empty($row['AccessKeyHash'])) {
            $this->recordLoginAttempt(
                $emailKey,
                $row['Id'] ?? null,
                $deviceName,
                $ipAddress,
                false,
            );
            throw new RuntimeException('invalid_credentials');
        }

        $computedHash = $this->hashAccessKey($accessKey);

        if (!hash_equals((string) $row['AccessKeyHash'], $computedHash)) {
            $this->recordLoginAttempt(
                $emailKey,
                $row['Id'],
                $deviceName,
                $ipAddress,
                false,
            );
            throw new RuntimeException('invalid_credentials');
        }

        if (empty($row['WaitingForToken'])) {
            $this->recordLoginAttempt(
                $emailKey,
                $row['Id'],
                $deviceName,
                $ipAddress,
                false,
            );
            throw new RuntimeException('customer_not_waiting');
        }

        $now = $this->now();
        $this->db->update(
            'Customers',
            [
                'DeviceName' => mb_substr($deviceName, 0, 160),
                'Token' => $newToken,
                'WaitingForToken' => 0,
                'WaitingSince' => null,
                'TokenUpdatedAt' => $now,
                'UpdatedAt' => $now,
                'IsActive' => isset($row['IsActive'])
                    ? (int) $row['IsActive']
                    : 1,
            ],
            'Id = ?',
            [$row['Id']],
        );
        $this->recordLoginAttempt(
            $emailKey,
            $row['Id'],
            $deviceName,
            $ipAddress,
            true,
        );

        return $this->find($row['Id'])
            ?? throw new RuntimeException('customer_not_found_after_login');
    }

    public function setWaitingForToken(
        string $customerId,
        bool $waiting,
    ): ?array
    {
        $customerId = trim($customerId);

        if ($this->raw($customerId) === null) {
            return null;
        }

        $now = $this->now();
        $this->db->update(
            'Customers',
            [
                'WaitingForToken' => $waiting ? 1 : 0,
                'WaitingSince' => $waiting ? $now : null,
                'UpdatedAt' => $now,
            ],
            'Id = ?',
            [$customerId],
        );

        return $this->find($customerId);
    }

    public function registerToken(
        string $customerId,
        string $token,
        ?string $deviceName,
    ): array
    {
        $row = $this->raw(trim($customerId));

        if ($row === null) {
            return ['status' => 'not_found'];
        }

        if (empty($row['WaitingForToken'])) {
            return [
                'status' => 'not_waiting',
                'customer' => $this->map($row),
            ];
        }

        $now = $this->now();
        $update = [
            'Token' => $token,
            'WaitingForToken' => 0,
            'WaitingSince' => null,
            'TokenUpdatedAt' => $now,
            'UpdatedAt' => $now,
            'IsActive' => isset($row['IsActive'])
                ? (int) $row['IsActive']
                : 1,
        ];

        if ($deviceName !== null) {
            $update['DeviceName'] = $deviceName === ''
                ? null
                : $deviceName;
        }

        $this->db->update(
            'Customers',
            $update,
            'Id = ?',
            [$customerId],
        );

        return [
            'status' => 'updated',
            'customer' => $this->find($customerId),
        ];
    }

    public function patch(string $customerId, array $attributes): ?array
    {
        if ($this->raw(trim($customerId)) === null) {
            return null;
        }

        return $this->save($customerId, $attributes);
    }

    public function updateClientVersion(
        string $customerId,
        string $version,
    ): ?array
    {
        if ($this->raw(trim($customerId)) === null) {
            return null;
        }

        $now = $this->now();
        $this->db->update(
            'Customers',
            [
                'ClientVersion' => trim($version),
                'ClientVersionUpdatedAt' => $now,
                'UpdatedAt' => $now,
            ],
            'Id = ?',
            [$customerId],
        );

        return $this->find($customerId);
    }

    public function storeJwt(string $customerId, string $jwt): void
    {
        $this->db->update(
            'Customers',
            [
                'TokenJwt' => $jwt,
                'TokenJwtCreatedAt' => $this->now(),
                'TokenJwtExpiresAt' => null,
            ],
            'Id = ?',
            [$customerId],
        );
    }

    public function jwtState(string $customerId): ?array
    {
        return $this->db->fetchOne(
            'SELECT Id, Token, TokenJwt, IsActive FROM Customers WHERE Id = ?',
            [$customerId],
        );
    }

    public function recordJwtAccess(
        string $customerId,
        ?string $ipAddress,
        ?string $deviceName,
        ?string $userAgent,
    ): void
    {
        $this->db->update(
            'Customers',
            ['LastSeen' => $this->now()],
            'Id = ?',
            [$customerId],
        );

        if ($ipAddress === null || trim($ipAddress) === '') {
            return;
        }

        $existing = $this->db->fetchOne(
            'SELECT Id FROM CustomerIpLogs WHERE CustomerId = ? AND IpAddress = ?',
            [
                $customerId,
                $ipAddress,
            ],
        );
        $now = $this->now();

        if ($existing !== null) {
            $sql = 'UPDATE CustomerIpLogs '
                . 'SET LastSeenAt = ?, AccessCount = AccessCount + 1, '
                . 'DeviceName = ?, UserAgent = ? WHERE Id = ?';
            $this->db->execute(
                $sql,
                [
                    $now,
                    $deviceName !== null ? mb_substr($deviceName, 0, 160) : null,
                    $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
                    $existing['Id'],
                ],
            );

            return;
        }

        $this->db->insert(
            'CustomerIpLogs',
            [
                'Id' => $this->guid(),
                'CustomerId' => $customerId,
                'IpAddress' => mb_substr($ipAddress, 0, 45),
                'City' => $this->isPrivateIp($ipAddress) ? 'Local' : null,
                'Region' => $this->isPrivateIp($ipAddress) ? 'Local Network' : null,
                'Country' => $this->isPrivateIp($ipAddress) ? 'Local' : null,
                'CountryCode' => $this->isPrivateIp($ipAddress) ? 'LO' : null,
                'Isp' => $this->isPrivateIp($ipAddress) ? 'Private Network' : null,
                'DeviceName' => $deviceName !== null
                    ? mb_substr($deviceName, 0, 160)
                    : null,
                'UserAgent' => $userAgent !== null
                    ? mb_substr($userAgent, 0, 500)
                    : null,
                'FirstSeenAt' => $now,
                'LastSeenAt' => $now,
                'AccessCount' => 1,
                'IsActive' => 1,
                'IsFlagged' => 0,
                'CreatedAt' => $now,
            ],
        );
    }

    public function countSuccessfulMessages(
        string $customerId,
        int $month,
        int $year,
    ): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM MessageSent '
                . 'WHERE Successful = 1 AND IsDebug = 0 '
                . 'AND CustomerApiId = ? AND MONTH(DateSent) = ? AND YEAR(DateSent) = ?',
            [
                $customerId,
                $month,
                $year,
            ],
        );

        return (int) ($row['total'] ?? 0);
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function createCustomer(string $customerId, array $attributes): array
    {
        do {
            $accessKey = $this->newAccessKey();
            $accessKeyHash = $this->hashAccessKey($accessKey);
        } while ($this->accessKeyHashExists($accessKeyHash));

        $attributes['accessKeyHash'] = $accessKeyHash;
        $this->assertEmailAvailable($attributes['email'] ?? null, $customerId);
        $now = $this->now();
        $token = $this->nullable($attributes['token'] ?? null);
        $codeAccess = isset($attributes['codeAccess'])
            ? $this->slug((string) $attributes['codeAccess'])
            : $this->uniqueCode((string) ($attributes['name'] ?? ''), $customerId);

        $this->db->insert(
            'Customers',
            [
                'Id' => $customerId,
                'BillingId' => $this->nullable($attributes['billingId'] ?? null),
                'PlanCode' => $this->nullable($attributes['planCode'] ?? null),
                'Name' => $attributes['name'] ?? null,
                'CodeAccess' => $codeAccess,
                'Email' => $attributes['email'] ?? null,
                'Phone' => $attributes['phone'] ?? null,
                'DeviceName' => $attributes['deviceName'] ?? null,
                'Token' => $token,
                'AccessKeyHash' => $accessKeyHash,
                'IsActive' => array_key_exists('isActive', $attributes)
                    ? ($attributes['isActive'] ? 1 : 0)
                    : 1,
                'WaitingForToken' => 0,
                'WaitingSince' => null,
                'TokenUpdatedAt' => $token !== null ? $now : null,
                'LastSeen' => null,
                'Metadata' => null,
                'CreatedAt' => $now,
                'UpdatedAt' => $now,
            ],
        );

        return $this->find($customerId)
            ?? throw new RuntimeException('customer_not_found_after_insert');
    }

    /** @param array<string, mixed> $consent */
    private function insertConsent(
        string $table,
        string $customerId,
        array $consent,
    ): void
    {
        $this->db->insert(
            $table,
            [
                'CustomerId' => $customerId,
                'DocumentVersion' => $consent['documentVersion'],
                'DocumentUrl' => $consent['documentUrl'],
                'AcceptedAt' => $consent['acceptedAt'],
                'IpAddress' => $consent['ipAddress'],
                'UserAgent' => $consent['userAgent'],
                'CreatedAt' => $this->now(),
            ],
        );
    }

    private function recordLoginAttempt(
        string $email,
        ?string $customerId,
        ?string $deviceName,
        ?string $ipAddress,
        bool $successful,
    ): void
    {
        $this->db->insert(
            'CustomerLoginAttempts',
            [
                'Email' => $email,
                'CustomerId' => $customerId,
                'IpAddress' => $ipAddress !== null
                    ? mb_substr($ipAddress, 0, 45)
                    : null,
                'DeviceName' => $deviceName !== null
                    ? mb_substr($deviceName, 0, 160)
                    : null,
                'WasSuccessful' => $successful ? 1 : 0,
                'CreatedAt' => $this->now(),
            ],
        );
    }

    private function assertEmailAvailable(
        ?string $email,
        ?string $ignoreCustomerId,
    ): void
    {
        if (!$this->isEmailAvailable($email, $ignoreCustomerId)) {
            throw new RuntimeException('email_already_registered');
        }
    }

    private function assertCodeAvailable(
        ?string $codeAccess,
        ?string $ignoreCustomerId,
    ): void
    {
        if ($codeAccess === null || $codeAccess === '') {
            return;
        }

        $params = [$codeAccess];
        $sql = 'SELECT Id FROM Customers WHERE CodeAccess = ?';

        if ($ignoreCustomerId !== null && $ignoreCustomerId !== '') {
            $sql .= ' AND Id <> ?';
            $params[] = $ignoreCustomerId;
        }

        $sql .= ' LIMIT 1';

        if ($this->db->fetchOne($sql, $params) !== null) {
            throw new RuntimeException('code_access_already_registered');
        }
    }

    private function uniqueCode(string $name, ?string $ignoreCustomerId): string
    {
        $base = $this->slug($name);
        $candidate = $base;

        while (!$this->codeAvailable($candidate, $ignoreCustomerId)) {
            $candidate = mb_substr($base, 0, 76)
                . '_'
                . substr(bin2hex(random_bytes(2)), 0, 3);
        }

        return $candidate;
    }

    private function codeAvailable(
        string $codeAccess,
        ?string $ignoreCustomerId,
    ): bool
    {
        $params = [$codeAccess];
        $sql = 'SELECT Id FROM Customers WHERE CodeAccess = ?';

        if ($ignoreCustomerId !== null && $ignoreCustomerId !== '') {
            $sql .= ' AND Id <> ?';
            $params[] = $ignoreCustomerId;
        }

        $sql .= ' LIMIT 1';

        return $this->db->fetchOne($sql, $params) === null;
    }

    private function accessKeyHashExists(string $hash): bool
    {
        return $this->db->fetchOne(
            'SELECT Id FROM Customers WHERE AccessKeyHash = ?',
            [$hash],
        ) !== null;
    }

    private function raw(string $customerId): ?array
    {
        if ($customerId === '') {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT * FROM Customers WHERE Id = ?',
            [$customerId],
        );
    }

    /** @return array<string, mixed> */
    private function map(array $row): array
    {
        return [
            'customerId' => $row['Id'],
            'billingId' => $row['BillingId'] ?? null,
            'planCode' => $row['PlanCode'] ?? null,
            'name' => $row['Name'],
            'codeAccess' => $row['CodeAccess'] ?? null,
            'email' => $row['Email'],
            'phone' => $row['Phone'],
            'deviceName' => $row['DeviceName'],
            'clientVersion' => $row['ClientVersion'] ?? null,
            'clientVersionUpdatedAt' => $this->timestamp(
                $row['ClientVersionUpdatedAt'] ?? null,
            ),
            'token' => $row['Token'],
            'isActive' => (bool) ($row['IsActive'] ?? 1),
            'waitingForToken' => (bool) ($row['WaitingForToken'] ?? 0),
            'waitingSince' => $this->timestamp($row['WaitingSince'] ?? null),
            'tokenUpdatedAt' => $this->timestamp($row['TokenUpdatedAt'] ?? null),
            'lastSeen' => $this->timestamp($row['LastSeen'] ?? null),
            'metadata' => !empty($row['Metadata'])
                ? (json_decode($row['Metadata'], true) ?: null)
                : null,
            'createdAt' => $this->timestamp($row['CreatedAt'] ?? null),
            'updatedAt' => $this->timestamp($row['UpdatedAt'] ?? null),
        ];
    }

    private function newCustomerId(): string
    {
        return 'cus_' . substr(bin2hex(random_bytes(16)), 0, 32);
    }

    private function newAccessKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segments = [];

        for ($segmentIndex = 0; $segmentIndex < 4; $segmentIndex++) {
            $segment = '';

            for ($characterIndex = 0; $characterIndex < 4; $characterIndex++) {
                $segment .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $segments[] = $segment;
        }

        return implode('-', $segments);
    }

    private function hashAccessKey(string $accessKey): string
    {
        $secret = getenv('ACCESS_KEY_SECRET');

        if ($secret === false || $secret === '') {
            $secret = $_ENV['ACCESS_KEY_SECRET']
                ?? $_SERVER['ACCESS_KEY_SECRET']
                ?? null;
        }

        if ($secret === null || $secret === '') {
            throw new RuntimeException('access_key_secret_missing');
        }

        return hash_hmac('sha512', $accessKey, (string) $secret);
    }

    private function slug(string $value): string
    {
        $asciiValue = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = strtolower(
            $asciiValue === false ? $value : $asciiValue,
        );
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        return $normalized !== ''
            ? mb_substr($normalized, 0, 80)
            : 'customer';
    }

    private function nullable(mixed $value): mixed
    {
        return is_string($value) && trim($value) === ''
            ? null
            : $value;
    }

    private function timestamp(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function guid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4),
        );
    }

    private function isPrivateIp(string $ipAddress): bool
    {
        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }
}
