<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\ListCards;

use App\Modules\Billing\Services\BillingOperations;

final readonly class Handler
{
    public function __construct(private BillingOperations $operations)
    {
    }

    public function handle(Query $query): mixed
    {
        return $this->operations->listCards($query->customerId, $query->billingId);
    }
}
