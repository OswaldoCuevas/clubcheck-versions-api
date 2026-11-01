<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\AddCard;

use App\Modules\Billing\Services\BillingOperations;

final readonly class Handler
{
    public function __construct(private BillingOperations $operations)
    {
    }

    public function handle(Command $command): mixed
    {
        return $this->operations->addCard($command->customerId, $command->billingId, $command->payload);
    }
}
