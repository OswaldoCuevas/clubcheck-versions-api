<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\PreviewActivePlanChange;

use App\Http\JsonResult;
use App\Modules\Billing\Services\BillingOperations;

final readonly class Handler
{
    public function __construct(private BillingOperations $operations)
    {
    }

    public function handle(Command $command): JsonResult
    {
        return $this->operations->previewActivePlan(
            $command->customerId,
            $command->payload,
        );
    }
}
