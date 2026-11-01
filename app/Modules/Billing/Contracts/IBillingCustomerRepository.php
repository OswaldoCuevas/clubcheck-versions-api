<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

interface IBillingCustomerRepository
{
    public function customer(string $customerId): ?array;
    public function findByBillingId(string $billingId): ?array;
    public function findByEmail(string $email): ?array;
    public function billingId(string $customerId, ?string $default = null): ?string;
    public function accessCodeByBillingId(string $billingId): ?string;
}
