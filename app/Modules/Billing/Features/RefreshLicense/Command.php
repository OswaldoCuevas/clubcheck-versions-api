<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\RefreshLicense;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public array $payload,
    ) {
    }
}
