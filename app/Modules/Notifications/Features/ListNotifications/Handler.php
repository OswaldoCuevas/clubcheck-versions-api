<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Features\ListNotifications;

use App\Modules\Notifications\Services\NotificationOperations;

final readonly class Handler
{
    public function __construct(private NotificationOperations $operations)
    {
    }

    public function handle(Query $query): array
    {
        return $this->operations->list(
            $query->customerId,
            $query->adminId,
            $query->parameters,
        );
    }
}
