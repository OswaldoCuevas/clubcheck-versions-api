<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Contracts;

interface IAdminAuthRepository
{
    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array;

    /** @return list<array<string, mixed>> */
    public function recentFailedAttempts(string $username, int $limit): array;

    public function recordAttempt(
        string $username,
        bool $successful,
        ?string $ipAddress,
        ?string $userAgent,
    ): void;

    public function clearFailedAttempts(string $username): void;

    /** @return list<string> */
    public function roles(int $userId): array;

    /** @return list<string> */
    public function permissions(int $userId): array;

    public function updateLastLogin(int $userId): void;

    public function saveRememberToken(int $userId, ?string $token): void;
}
