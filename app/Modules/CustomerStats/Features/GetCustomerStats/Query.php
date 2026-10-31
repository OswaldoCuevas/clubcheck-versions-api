<?php

declare(strict_types=1);

namespace App\Modules\CustomerStats\Features\GetCustomerStats;

final readonly class Query
{
    public function __construct(public string $customerId)
    {
    }
}
