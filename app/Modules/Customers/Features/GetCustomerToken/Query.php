<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\GetCustomerToken;

final readonly class Query
{
    public function __construct(public ?string $customerId)
    {
    }
}
