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

    public function administrator(string $customerId, string $adminId): ?array;

    public function listForAdministrator(
        string $customerId,
        string $adminId,
        bool $unreadOnly,
        int $limit,
        int $offset,
    ): array;

    public function countForAdministrator(
        string $customerId,
        string $adminId,
        bool $unreadOnly,
    ): int;

    public function unreadCount(string $customerId, string $adminId): int;

    public function markRead(string $id, string $customerId, string $adminId): ?array;

    public function markAllRead(string $customerId, string $adminId): int;
}
