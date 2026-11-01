<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\GetCustomer;

final readonly class Query
{
    public function __construct(
        public ?string $requestedCustomerId,
        public ?string $authenticatedCustomerId,
    ) {
    }
}
