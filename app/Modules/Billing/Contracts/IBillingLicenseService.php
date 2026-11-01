<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

interface IBillingLicenseService
{
    public function available(): bool;
    public function verify(string $token): array;
    public function attach(
        array $result,
        array $customer,
        string $planKey,
        string $planName,
        bool $permanent,
        ?int $expiresAt,
    ): array;
}
