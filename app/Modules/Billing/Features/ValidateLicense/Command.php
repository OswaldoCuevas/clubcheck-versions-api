<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\ValidateLicense;

final readonly class Command
{
    public function __construct(
        public array $payload,
    ) {
    }
}
