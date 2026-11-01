<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Features\ListUserSubscriptions;

final readonly class Query
{
    public function __construct(
        public string $customerId,
        public string $userId,
        public array $parameters,
    ) {
    }
}
