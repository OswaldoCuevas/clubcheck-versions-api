<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\SaveCustomer;

final readonly class Command
{
    public function __construct(public array $payload)
    {
    }
}
