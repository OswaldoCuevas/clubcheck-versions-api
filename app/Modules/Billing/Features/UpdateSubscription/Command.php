<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\UpdateSubscription;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public string $subscriptionId,
        public array $payload,
    ) {
    }
}
