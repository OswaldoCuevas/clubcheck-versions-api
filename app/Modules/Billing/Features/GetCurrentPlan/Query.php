<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\GetCurrentPlan;

final readonly class Query
{
    public function __construct(
        public string $customerId,
        public string $billingId,
    ) {
    }
}
