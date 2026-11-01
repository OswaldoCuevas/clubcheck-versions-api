<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\ValidateCoupon;

final readonly class Command
{
    public function __construct(
        public array $payload,
    ) {
    }
}
