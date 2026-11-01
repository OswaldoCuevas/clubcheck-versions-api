<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

interface ICustomerApiRepository
{
    /** @return array<string, mixed>|null */
    public function find(string $customerId): ?array;

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function save(string $customerId, array $attributes): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function register(array $data): array;

    public function isEmailAvailable(?string $email, ?string $ignoreCustomerId = null): bool;

    /** @return array<string, mixed> */
    public function login(
        string $email,
        string $accessKey,
        string $deviceName,
        ?string $ipAddress,
        string $newToken,
    ): array;

    /** @return array<string, mixed>|null */
    public function setWaitingForToken(string $customerId, bool $waiting): ?array;

    /** @return array<string, mixed> */
    public function registerToken(
        string $customerId,
        string $token,
        ?string $deviceName,
    ): array;

    /** @param array<string, mixed> $attributes @return array<string, mixed>|null */
    public function patch(string $customerId, array $attributes): ?array;

    /** @return array<string, mixed>|null */
    public function updateClientVersion(string $customerId, string $version): ?array;

    public function storeJwt(string $customerId, string $jwt): void;

    /** @return array<string, mixed>|null */
    public function jwtState(string $customerId): ?array;

    public function recordJwtAccess(
        string $customerId,
        ?string $ipAddress,
        ?string $deviceName,
        ?string $userAgent,
    ): void;

    public function countSuccessfulMessages(
        string $customerId,
        int $month,
        int $year,
    ): int;
}
