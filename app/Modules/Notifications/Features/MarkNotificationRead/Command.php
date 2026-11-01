<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Features\MarkNotificationRead;

final readonly class Command
{
    public function __construct(
        public string $customerId,
        public string $adminId,
        public string $id,
    ) {
    }
}
