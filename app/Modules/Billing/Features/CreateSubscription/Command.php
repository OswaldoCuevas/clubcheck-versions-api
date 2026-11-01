<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\CreateSubscription;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public string $billingId,
        public array $payload,
        public ?string $idempotencyKey,
    ) {
    }
}
