<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\DeleteCustomer;

final readonly class Command
{
    public function __construct(public string $customerId)
    {
    }
}
