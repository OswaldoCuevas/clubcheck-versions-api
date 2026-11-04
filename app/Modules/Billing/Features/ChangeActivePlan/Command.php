<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\ChangeActivePlan;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public array $payload,
    )
    {
    }
}
