<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\ListCards;

final readonly class Query
{
    public function __construct(
        public string $customerId,
        public string $billingId,
    ) {
    }
}
