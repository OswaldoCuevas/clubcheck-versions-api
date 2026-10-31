<?php

declare(strict_types=1);

namespace App\Modules\Licenses\Contracts;

interface ILicenseRepository
{
    public function all(): array;
    public function log(array $data): int;
    public function customer(string $id): ?array;
    public function customerTokenRow(string $id): ?array;
    public function storeCustomerJwt(string $id, string $jwt): void;
}
