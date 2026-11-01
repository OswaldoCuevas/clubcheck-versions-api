<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

final class CustomerBillingContext
{
    public function customerId(): string
    {
        return (string) ($GLOBALS['customer_jwt_customer_id'] ?? '');
    }
}
