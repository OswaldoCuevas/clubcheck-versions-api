<?php

namespace Models;

require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class CustomerRegistryModel extends Model
{
    protected function initialize()
    {
        // No se requiere inicialización adicional con almacenamiento en base de datos.
    }

    private function normaliseCustomerId(string $customerId): string
    {
        return trim($customerId);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function generateCustomerId(): string
    {
        try {
            $random = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $random = hash('sha256', uniqid('', true));
        }

        return 'cus_' . substr($random, 0, 32);
    }

    private function generateAccessKey(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segments = [];

        for ($i = 0; $i < 4; $i++) {
            $segment = '';
            for ($j = 0; $j < 4; $j++) {
                $segment .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $segments[] = $segment;
        }

        return implode('-', $segments);
    }

    private function getAccessKeySecret(): string
    {
        $secret = getenv('ACCESS_KEY_SECRET');
        if ($secret === false || $secret === null || $secret === '') {
            $secret = $_ENV['ACCESS_KEY_SECRET'] ?? $_SERVER['ACCESS_KEY_SECRET'] ?? null;
        }

        if ($secret === null || $secret === '') {
            throw new \RuntimeException('access_key_secret_missing');
        }

        return (string) $secret;
    }

    private function hashAccessKey(string $accessKey): string
    {
        $secret = $this->getAccessKeySecret();

        return hash_hmac('sha512', $accessKey, $secret);
    }

    private function accessKeyHashExists(string $accessKeyHash): bool
    {
        $row = $this->db->fetchOne(
            'SELECT Id FROM Customers WHERE AccessKeyHash = ?',
            [$accessKeyHash]
        );

        return $row !== null;
    }

    private function normalizeLoginEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function countRecentFailedLoginAttempts(string $emailKey, int $seconds): int
    {
        $since = date('Y-m-d H:i:s', time() - $seconds);

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM CustomerLoginAttempts WHERE Email = ? AND WasSuccessful = 0 AND CreatedAt >= ?',
            [$emailKey, $since]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function recordLoginAttempt(string $emailKey, ?string $customerId, ?string $deviceName, ?string $ipAddress, bool $wasSuccessful): void
    {
        $data = [
            'Email' => $emailKey,
            'CustomerId' => $customerId,
            'IpAddress' => $ipAddress !== null && $ipAddress !== '' ? mb_substr($ipAddress, 0, 45) : null,
            'DeviceName' => $deviceName !== null && $deviceName !== '' ? mb_substr($deviceName, 0, 160) : null,
            'WasSuccessful' => $wasSuccessful ? 1 : 0,
            'CreatedAt' => $this->now(),
        ];

        $this->db->insert('CustomerLoginAttempts', $data);
    }

    private function toDateTime(?int $timestamp): ?string
    {
        if ($timestamp === null) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function fromDateTime(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $time = strtotime($value);

        return $time === false ? null : $time;
    }

    private function decodeMetadata(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function encodeMetadata($metadata): ?string
    {
        if ($metadata === null) {
            return null;
        }

        if (is_array($metadata)) {
            $json = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return json_last_error() === JSON_ERROR_NONE ? $json : null;
        }

        return (string) $metadata;
    }

    private function normalizePrivacyAcceptance(array $payload): array
    {
        $documentVersion = isset($payload['documentVersion']) ? trim((string) $payload['documentVersion']) : '';
        if ($documentVersion === '') {
            throw new \InvalidArgumentException('privacy_document_version_required');
        }
        if (mb_strlen($documentVersion) > 50) {
            throw new \InvalidArgumentException('privacy_document_version_too_long');
        }

        $documentUrl = isset($payload['documentUrl']) ? trim((string) $payload['documentUrl']) : '';
        if ($documentUrl === '') {
            throw new \InvalidArgumentException('privacy_document_url_required');
        }
        if (mb_strlen($documentUrl) > 255) {
            throw new \InvalidArgumentException('privacy_document_url_too_long');
        }

        $ipAddress = isset($payload['ipAddress']) ? trim((string) $payload['ipAddress']) : '';
        if ($ipAddress === '') {
            throw new \InvalidArgumentException('privacy_ip_required');
        }
        if (mb_strlen($ipAddress) > 45) {
            throw new \InvalidArgumentException('privacy_ip_too_long');
        }

        $acceptedAtRaw = $payload['acceptedAt'] ?? null;
        if ($acceptedAtRaw === null || $acceptedAtRaw === '') {
            $acceptedAt = $this->now();
        } elseif (is_numeric($acceptedAtRaw)) {
            $acceptedAt = $this->toDateTime((int) $acceptedAtRaw);
            if ($acceptedAt === null) {
                throw new \InvalidArgumentException('privacy_invalid_accepted_at');
            }
        } else {
            $timestamp = strtotime((string) $acceptedAtRaw);
            if ($timestamp === false) {
                throw new \InvalidArgumentException('privacy_invalid_accepted_at');
            }
            $acceptedAt = date('Y-m-d H:i:s', $timestamp);
        }

        $userAgent = isset($payload['userAgent']) ? trim((string) $payload['userAgent']) : null;
        if ($userAgent !== null && $userAgent === '') {
            $userAgent = null;
        }
        if ($userAgent !== null && mb_strlen($userAgent) > 255) {
            $userAgent = mb_substr($userAgent, 0, 255);
        }

        return [
            'documentVersion' => $documentVersion,
            'documentUrl' => $documentUrl,
            'acceptedAt' => $acceptedAt,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
        ];
    }

    private function hydrateCustomer(array $row): array
    {
        return [
            'customerId' => $row['Id'],
            'billingId' => $row['BillingId'] ?? null,
            'planCode' => $row['PlanCode'] ?? null,
            'name' => $row['Name'],
            'email' => $row['Email'],
            'phone' => $row['Phone'],
            'deviceName' => $row['DeviceName'],
            'token' => $row['Token'],
            'isActive' => (bool) ($row['IsActive'] ?? 1),
            'waitingForToken' => (bool) ($row['WaitingForToken'] ?? 0),
            'waitingSince' => $this->fromDateTime($row['WaitingSince'] ?? null),
            'tokenUpdatedAt' => $this->fromDateTime($row['TokenUpdatedAt'] ?? null),
            'lastSeen' => $this->fromDateTime($row['LastSeen'] ?? null),
            'metadata' => $this->decodeMetadata($row['Metadata'] ?? null),
            'createdAt' => $this->fromDateTime($row['CreatedAt'] ?? null),
            'updatedAt' => $this->fromDateTime($row['UpdatedAt'] ?? null),
        ];
    }

    private function findRawCustomer(string $customerId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM Customers WHERE Id = ?',
            [$customerId]
        );
    }

    private function findCustomerByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM Customers WHERE Email = ?',
            [$email]
        );
    }

    private function assertEmailAvailable(?string $email, ?string $ignoreCustomerId = null): void
    {
        if ($email === null || $email === '') {
            return;
        }

        $existing = $this->findCustomerByEmail($email);

        if ($existing === null) {
            return;
        }

        if ($ignoreCustomerId !== null && $existing['Id'] === $ignoreCustomerId) {
            return;
        }

        throw new \RuntimeException('email_already_registered');
    }

    public function isEmailAvailable(?string $email, ?string $ignoreCustomerId = null): bool
    {
        if ($email === null || $email === '') {
            return true;
        }

        $existing = $this->findCustomerByEmail($email);

        if ($existing === null) {
            return true;
        }

        if ($ignoreCustomerId !== null) {
            $ignoreCustomerId = $this->normaliseCustomerId($ignoreCustomerId);
            if ($ignoreCustomerId !== '' && $existing['Id'] === $ignoreCustomerId) {
                return true;
            }
        }

        return false;
    }

    public function getCustomers(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM Customers ORDER BY Name ASC, Id ASC');

        return array_map(fn ($row) => $this->hydrateCustomer($row), $rows);
    }

    public function getCustomer(string $customerId): ?array
    {
        $customerId = $this->normaliseCustomerId($customerId);

        if ($customerId === '') {
            return null;
        }

        $row = $this->findRawCustomer($customerId);

        return $row ? $this->hydrateCustomer($row) : null;
    }

    public function upsertCustomer(string $customerId, array $attributes): array
    {
        $customerId = $this->normaliseCustomerId($customerId);

        if ($customerId === '') {
            throw new \InvalidArgumentException('customerId is required');
        }

        $existing = $this->findRawCustomer($customerId);

        if ($existing === null) {
            $accessKey = null;
            $accessKeyHash = null;

            do {
                $accessKey = $this->generateAccessKey();
                $accessKeyHash = $this->hashAccessKey($accessKey);
            } while ($this->accessKeyHashExists($accessKeyHash));

            $attributes['accessKeyHash'] = $accessKeyHash;

            $customer = $this->insertCustomer($customerId, $attributes);

            return $customer;
        }

        $update = [];

        if (array_key_exists('name', $attributes)) {
            $update['Name'] = $attributes['name'];
        }

        if (array_key_exists('billingId', $attributes)) {
            $billingId = is_string($attributes['billingId']) ? trim($attributes['billingId']) : $attributes['billingId'];
            $update['BillingId'] = ($billingId === null || $billingId === '') ? null : $billingId;
        }

        if (array_key_exists('planCode', $attributes)) {
            $planCode = is_string($attributes['planCode']) ? trim($attributes['planCode']) : $attributes['planCode'];
            $update['PlanCode'] = ($planCode === null || $planCode === '') ? null : $planCode;
        }

        if (array_key_exists('email', $attributes)) {
            $update['Email'] = $attributes['email'];
            $this->assertEmailAvailable($update['Email'], $customerId);
        }

        if (array_key_exists('phone', $attributes)) {
            $update['Phone'] = $attributes['phone'];
        }

        if (array_key_exists('deviceName', $attributes)) {
            $update['DeviceName'] = $attributes['deviceName'];
        }

        if (array_key_exists('token', $attributes)) {
            $token = $attributes['token'];
            $update['Token'] = ($token === null || $token === '') ? null : $token;
            $update['TokenUpdatedAt'] = ($token === null || $token === '') ? null : $this->now();
        }

        if (array_key_exists('isActive', $attributes)) {
            $update['IsActive'] = $attributes['isActive'] ? 1 : 0;
        }

        if (array_key_exists('waitingForToken', $attributes)) {
            $update['WaitingForToken'] = $attributes['waitingForToken'] ? 1 : 0;
        }

        if (array_key_exists('waitingSince', $attributes)) {
            $update['WaitingSince'] = $this->toDateTime($attributes['waitingSince']);
        }

        if (array_key_exists('tokenUpdatedAt', $attributes)) {
            $update['TokenUpdatedAt'] = $this->toDateTime($attributes['tokenUpdatedAt']);
        }

        if (array_key_exists('lastSeen', $attributes)) {
            $update['LastSeen'] = $this->toDateTime($attributes['lastSeen']);
        }

        if (array_key_exists('metadata', $attributes)) {
            $update['Metadata'] = $this->encodeMetadata($attributes['metadata']);
        }

        if (!empty($update)) {
            $update['UpdatedAt'] = $this->now();
            $this->db->update('Customers', $update, 'Id = ?', [$customerId]);
        }

        return $this->getCustomer($customerId);
    }

    private function insertCustomer(string $customerId, array $attributes): array
    {
        $now = $this->now();

        $token = $attributes['token'] ?? null;
        $token = ($token === null || $token === '') ? null : $token;

        $data = [
            'Id' => $customerId,
            'BillingId' => isset($attributes['billingId']) && $attributes['billingId'] !== ''
                ? (is_string($attributes['billingId']) ? trim($attributes['billingId']) : $attributes['billingId'])
                : null,
            'PlanCode' => isset($attributes['planCode']) && $attributes['planCode'] !== ''
                ? (is_string($attributes['planCode']) ? trim($attributes['planCode']) : $attributes['planCode'])
                : null,
            'Name' => $attributes['name'] ?? null,
            'Email' => $attributes['email'] ?? null,
            'Phone' => $attributes['phone'] ?? null,
            'DeviceName' => $attributes['deviceName'] ?? null,
            'Token' => $token,
            'AccessKeyHash' => $attributes['accessKeyHash'] ?? null,
            'IsActive' => array_key_exists('isActive', $attributes) ? ($attributes['isActive'] ? 1 : 0) : 1,
            'WaitingForToken' => array_key_exists('waitingForToken', $attributes) ? ($attributes['waitingForToken'] ? 1 : 0) : 0,
            'WaitingSince' => isset($attributes['waitingSince']) ? $this->toDateTime($attributes['waitingSince']) : null,
            'TokenUpdatedAt' => $token !== null ? $now : null,
            'LastSeen' => isset($attributes['lastSeen']) ? $this->toDateTime($attributes['lastSeen']) : null,
            'Metadata' => isset($attributes['metadata']) ? $this->encodeMetadata($attributes['metadata']) : null,
            'CreatedAt' => $now,
            'UpdatedAt' => $now,
        ];

        if ($data['AccessKeyHash'] === null || $data['AccessKeyHash'] === '') {
            throw new \InvalidArgumentException('access_key_hash_required');
        }

        $this->db->insert('Customers', $data);

        return $this->getCustomer($customerId);
    }

    private function insertPrivacyConsent(string $customerId, array $consent): void
    {
        $this->db->insert('CustomerPrivacyConsents', [
            'CustomerId' => $customerId,
            'DocumentVersion' => $consent['documentVersion'],
            'DocumentUrl' => $consent['documentUrl'],
            'AcceptedAt' => $consent['acceptedAt'],
            'IpAddress' => $consent['ipAddress'],
            'UserAgent' => $consent['userAgent'],
            'CreatedAt' => $this->now(),
        ]);
    }

    public function registerCustomerIfAbsent(array $payload): array
    {
        $specifiedId = $this->normaliseCustomerId($payload['customerId'] ?? '');

        if ($specifiedId !== '') {
            $existing = $this->findRawCustomer($specifiedId);

            if ($existing !== null) {
                return [
                    'found' => true,
                    'customer' => $this->hydrateCustomer($existing),
                ];
            }
        }

        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';

        if ($name === '') {
            throw new \InvalidArgumentException('name is required');
        }

        $customerId = $specifiedId !== '' ? $specifiedId : $this->generateCustomerId();

        $email = isset($payload['email']) && $payload['email'] !== '' ? trim((string) $payload['email']) : null;

        $this->assertEmailAvailable($email, $specifiedId !== '' ? $specifiedId : null);

        if (!isset($payload['privacyAcceptance']) || !is_array($payload['privacyAcceptance'])) {
            throw new \InvalidArgumentException('privacy_acceptance_required');
        }

        $privacyConsent = $this->normalizePrivacyAcceptance($payload['privacyAcceptance']);

        $accessKey = null;
        $accessKeyHash = null;

        do {
            $accessKey = $this->generateAccessKey();
            $accessKeyHash = $this->hashAccessKey($accessKey);
        } while ($this->accessKeyHashExists($accessKeyHash));

        $customer = $this->insertCustomer($customerId, [
            'billingId' => isset($payload['billingId']) && $payload['billingId'] !== '' ? trim((string) $payload['billingId']) : null,
            'planCode' => isset($payload['planCode']) && $payload['planCode'] !== '' ? trim((string) $payload['planCode']) : null,
            'name' => $name,
            'email' => $email,
            'phone' => isset($payload['phone']) && $payload['phone'] !== '' ? trim((string) $payload['phone']) : null,
            'deviceName' => isset($payload['deviceName']) && $payload['deviceName'] !== '' ? trim((string) $payload['deviceName']) : null,
            'token' => isset($payload['token']) ? trim((string) $payload['token']) : null,
            'isActive' => array_key_exists('isActive', $payload) ? (bool) $payload['isActive'] : true,
            'accessKeyHash' => $accessKeyHash,
        ]);

        $this->insertPrivacyConsent($customerId, $privacyConsent);

        return [
            'found' => false,
            'customer' => $customer,
            'accessKey' => $accessKey,
        ];
    }

    public function loginWithAccessKey(string $email, string $accessKey, ?string $deviceName = null, ?string $ipAddress = null, ?string $newToken = null): array
    {
        $email = trim((string) $email);
        $accessKey = trim((string) $accessKey);
        $deviceName = $deviceName !== null ? trim((string) $deviceName) : null;
        $ipAddress = $ipAddress !== null ? trim((string) $ipAddress) : null;
        $newToken = $newToken !== null ? trim((string) $newToken) : null;

        if ($email === '' || $accessKey === '') {
            throw new \InvalidArgumentException('email_and_access_key_required');
        }

        $emailKey = $this->normalizeLoginEmail($email);
        if ($emailKey === '') {
            throw new \InvalidArgumentException('email_and_access_key_required');
        }

        if ($this->countRecentFailedLoginAttempts($emailKey, 3600) >= 5) {
            throw new \RuntimeException('too_many_attempts');
        }

        $row = $this->findCustomerByEmail($email);
        if ($row === null) {
            $this->recordLoginAttempt($emailKey, null, $deviceName, $ipAddress, false);
            throw new \RuntimeException('invalid_credentials');
        }

        if (empty($row['AccessKeyHash'])) {
            $this->recordLoginAttempt($emailKey, $row['Id'], $deviceName, $ipAddress, false);
            throw new \RuntimeException('invalid_credentials');
        }

        $computedHash = $this->hashAccessKey($accessKey);

        if (!hash_equals($row['AccessKeyHash'], $computedHash)) {
            $this->recordLoginAttempt($emailKey, $row['Id'], $deviceName, $ipAddress, false);
            throw new \RuntimeException('invalid_credentials');
        }

        if (empty($row['WaitingForToken'])) {
            $this->recordLoginAttempt($emailKey, $row['Id'], $deviceName, $ipAddress, false);
            throw new \RuntimeException('customer_not_waiting');
        }

        $customerId = $row['Id'];

        if ($deviceName !== null && $deviceName !== '') {
            $normalizedDeviceName = mb_substr($deviceName, 0, 160);
            if ($row['DeviceName'] !== $normalizedDeviceName) {
                $this->db->update(
                    'Customers',
                    [
                        'DeviceName' => $normalizedDeviceName,
                        'UpdatedAt' => $this->now(),
                    ],
                    'Id = ?',
                    [$customerId]
                );

                $row['DeviceName'] = $normalizedDeviceName;
            }
        }

        if ($newToken !== null && $newToken !== '') {
            $this->db->update(
                'Customers',
                [
                    'Token' => $newToken,
                    'WaitingForToken' => 0,
                    'WaitingSince' => null,
                    'TokenUpdatedAt' => $this->now(),
                    'UpdatedAt' => $this->now(),
                    'IsActive' => isset($row['IsActive']) ? (int) $row['IsActive'] : 1,
                ],
                'Id = ?',
                [$customerId]
            );
        }

        $this->recordLoginAttempt($emailKey, $customerId, $deviceName, $ipAddress, true);

        $fresh = $this->findRawCustomer($customerId);

        return $fresh !== null ? $this->hydrateCustomer($fresh) : $this->hydrateCustomer($row);
    }

    public function setWaitingForToken(string $customerId, bool $waiting): ?array
    {
        $customerId = $this->normaliseCustomerId($customerId);

        if ($customerId === '') {
            return null;
        }

        $row = $this->findRawCustomer($customerId);

        if ($row === null) {
            return null;
        }

        $update = [
            'WaitingForToken' => $waiting ? 1 : 0,
            'WaitingSince' => $waiting ? $this->now() : null,
            'UpdatedAt' => $this->now(),
        ];

        $this->db->update('Customers', $update, 'Id = ?', [$customerId]);

        return $this->getCustomer($customerId);
    }

    public function setActiveStatus(string $customerId, bool $isActive): ?array
    {
        $customerId = $this->normaliseCustomerId($customerId);

        if ($customerId === '') {
            return null;
        }

        $row = $this->findRawCustomer($customerId);

        if ($row === null) {
            return null;
        }

        $this->db->update(
            'Customers',
            [
                'IsActive' => $isActive ? 1 : 0,
                'UpdatedAt' => $this->now(),
            ],
            'Id = ?',
            [$customerId]
        );

        return $this->getCustomer($customerId);
    }

    public function registerToken(string $customerId, string $token, ?string $deviceName = null): array
    {
        $customerId = $this->normaliseCustomerId($customerId);

        if ($customerId === '') {
            return [
                'status' => 'not_found',
            ];
        }

        $row = $this->findRawCustomer($customerId);

        if ($row === null) {
            return [
                'status' => 'not_found',
            ];
        }

        if (empty($row['WaitingForToken'])) {
            return [
                'status' => 'not_waiting',
                'customer' => $this->hydrateCustomer($row),
            ];
        }

        $update = [
            'Token' => $token,
            'WaitingForToken' => 0,
            'WaitingSince' => null,
            'TokenUpdatedAt' => $this->now(),
            'UpdatedAt' => $this->now(),
            'IsActive' => isset($row['IsActive']) ? (int) $row['IsActive'] : 1,
        ];

        if ($deviceName !== null) {
            $update['DeviceName'] = $deviceName === '' ? null : $deviceName;
        }

        $this->db->update('Customers', $update, 'Id = ?', [$customerId]);

        return [
            'status' => 'updated',
            'customer' => $this->getCustomer($customerId),
        ];
    }

    public function patchCustomerAttributes(string $customerId, array $attributes): ?array
    {
        $customerId = $this->normaliseCustomerId($customerId);

        if ($customerId === '') {
            return null;
        }

        $row = $this->findRawCustomer($customerId);

        if ($row === null) {
            return null;
        }

        $update = [];

        if (array_key_exists('name', $attributes)) {
            $name = $attributes['name'];
            if ($name !== null) {
                $name = trim((string) $name);
            }
            $update['Name'] = ($name === null || $name === '') ? null : $name;
        }

        if (array_key_exists('email', $attributes)) {
            $email = $attributes['email'];
            if ($email !== null) {
                $email = trim((string) $email);
            }
            $email = ($email === null || $email === '') ? null : $email;
            $this->assertEmailAvailable($email, $customerId);
            $update['Email'] = $email;
        }

        if (array_key_exists('phone', $attributes)) {
            $phone = $attributes['phone'];
            if ($phone !== null) {
                $phone = trim((string) $phone);
            }
            $update['Phone'] = ($phone === null || $phone === '') ? null : $phone;
        }

        if (array_key_exists('planCode', $attributes)) {
            $planCode = $attributes['planCode'];
            if ($planCode !== null) {
                $planCode = trim((string) $planCode);
            }
            $update['PlanCode'] = ($planCode === null || $planCode === '') ? null : $planCode;
        }

        if (empty($update)) {
            return $this->getCustomer($customerId);
        }

        $update['UpdatedAt'] = $this->now();
        $this->db->update('Customers', $update, 'Id = ?', [$customerId]);

        return $this->getCustomer($customerId);
    }

    public function regenerateAccessKey(string $customerId): ?array
    {
        $customerId = $this->normaliseCustomerId($customerId);

        if ($customerId === '') {
            return null;
        }

        $row = $this->findRawCustomer($customerId);

        if ($row === null) {
            return null;
        }

        $accessKey = null;
        $accessKeyHash = null;

        do {
            $accessKey = $this->generateAccessKey();
            $accessKeyHash = $this->hashAccessKey($accessKey);
        } while ($this->accessKeyHashExists($accessKeyHash));

        $update = [
            'AccessKeyHash' => $accessKeyHash,
            'UpdatedAt' => $this->now(),
        ];

        $this->db->update('Customers', $update, 'Id = ?', [$customerId]);

        return [
            'customerId' => $customerId,
            'accessKey' => $accessKey,
            'customer' => $this->getCustomer($customerId),
        ];
    }
}
