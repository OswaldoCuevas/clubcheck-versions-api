<?php

declare(strict_types=1);

namespace App\Modules\PushNotifications\Contracts;

interface IPushTokenRepository
{
    public function customersWithDeviceCounts(): array;
    public function customerExists(string $customerId): bool;
    public function register(string $customerId, string $token, ?string $platform): void;
    public function remove(string $customerId, string $token): bool;
    public function removeInvalid(string $id, string $token): void;
    public function forCustomer(string $customerId, ?string $platform = null): array;
}
