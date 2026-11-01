<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Features\ListNotifications;

final readonly class Query
{
    public function __construct(
        public string $customerId,
        public string $adminId,
        public array $parameters,
    ) {
    }
}
