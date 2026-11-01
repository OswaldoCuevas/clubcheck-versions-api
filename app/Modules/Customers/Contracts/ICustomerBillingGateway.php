<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

interface ICustomerBillingGateway
{
    /** @return array<string, mixed> */
    public function createCustomer(
        string $name,
        string $email,
        ?string $phone,
    ): array;

    /** @return array<string, mixed> */
    public function activeSubscription(string $billingId): array;
}
