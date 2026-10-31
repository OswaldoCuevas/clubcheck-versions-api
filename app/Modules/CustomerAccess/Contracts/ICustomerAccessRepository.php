<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Contracts;

interface ICustomerAccessRepository
{
    public function loginAttempts(array $filters, int $page, int $perPage): array;
    public function loginSummary(): array;
    public function jwtStats(): array;
    public function customerIpSummary(): array;
    public function customerIps(string $customerId): array;
    public function hasMultipleRecentIps(string $customerId): bool;
    public function flagIp(string $id, bool $flagged, ?string $reason): bool;
    public function tokenCustomer(string $customerId): ?array;
    public function storeToken(string $customerId, string $jwt, ?int $expiresAt): void;
    public function revokeToken(string $customerId): bool;
}
