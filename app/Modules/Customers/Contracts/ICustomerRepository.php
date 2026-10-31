<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

interface ICustomerRepository
{
    public function getAll(): array;
    public function find(string $customerId): ?array;
    public function save(string $customerId, array $attributes): array;
    public function regenerateAccessKey(string $customerId): ?array;
    public function delete(string $customerId): bool;
}
