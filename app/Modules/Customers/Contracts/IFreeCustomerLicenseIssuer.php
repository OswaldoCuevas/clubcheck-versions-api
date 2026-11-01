<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

interface IFreeCustomerLicenseIssuer
{
    /** @return array<string, string> */
    public function issue(
        string $billingId,
        string $customerName,
        ?string $customerEmail,
        string $machineToken,
        string $customerId,
    ): array;
}
