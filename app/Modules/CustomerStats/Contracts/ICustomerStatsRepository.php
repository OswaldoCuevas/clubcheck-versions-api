<?php

declare(strict_types=1);

namespace App\Modules\CustomerStats\Contracts;

interface ICustomerStatsRepository
{
    public function global(): array;
    public function all(): array;
    public function forCustomer(string $id): array;
    public function findCustomer(string $id): ?array;
}
