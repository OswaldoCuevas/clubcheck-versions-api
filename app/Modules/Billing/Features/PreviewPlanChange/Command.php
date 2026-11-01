<?php

declare(strict_types=1);

namespace App\Modules\Billing\Features\PreviewPlanChange;

final readonly class Command
{
    public function __construct(
        public string $subscriptionId,
        public array $payload,
    ) {
    }
}
