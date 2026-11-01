<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

interface ICustomerJwtService
{
    public function create(string $customerId, string $machineToken): string;

    /** @return array<string, mixed> */
    public function validate(
        string $jwt,
        ?string $ipAddress,
        ?string $deviceName,
        ?string $userAgent,
    ): array;
}
