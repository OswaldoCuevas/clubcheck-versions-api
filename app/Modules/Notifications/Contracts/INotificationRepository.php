<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Contracts;

interface INotificationRepository
{
    public function createForPrivilegedAdministrators(
        string $customerId,
        string $type,
        string $title,
        string $message,
        ?string $permissionRequestId = null,
        ?string $screen = null,
        ?string $recordId = null,
    ): int;
}
