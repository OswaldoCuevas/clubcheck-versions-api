<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\PreviewNewSubscription;

use App\Modules\Billing\Services\BillingOperations;

final readonly class Handler
{
    public function __construct(private BillingOperations $operations)
    {
    }

    public function handle(Command $command): mixed
    {
        return $this->operations->previewSubscription($command->customerId, $command->billingId, $command->payload);
    }
}
