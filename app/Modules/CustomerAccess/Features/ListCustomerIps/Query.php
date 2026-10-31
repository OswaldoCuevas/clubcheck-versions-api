<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\ListCustomerIps;

final readonly class Query
{
    public function __construct(public string $customerId)
    {
    }
}
