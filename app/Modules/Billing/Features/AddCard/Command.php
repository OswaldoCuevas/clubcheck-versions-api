<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\AddCard;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public string $billingId,
        public array $payload,
    ) {
    }
}
