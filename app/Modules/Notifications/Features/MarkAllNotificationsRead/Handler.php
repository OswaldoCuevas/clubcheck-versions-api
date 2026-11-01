<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Features\MarkAllNotificationsRead;

use App\Modules\Notifications\Services\NotificationOperations;

final readonly class Handler
{
    public function __construct(private NotificationOperations $operations)
    {
    }

    public function handle(Command $command): array
    {
        return $this->operations->markAllRead(
            $command->customerId,
            $command->adminId,
        );
    }
}
