<?php

declare(strict_types=1);

namespace App\Modules\PermissionRequests\Contracts;

interface IPermissionRequestRepository
{
    public function administratorForCustomer(string $customerId, string $adminId): ?array;
    public function create(
        string $customerId,
        string $requesterId,
        string $title,
        string $description,
        string $screen,
        string $recordId,
    ): array;
    public function find(string $id, string $customerId): ?array;
    public function list(string $customerId, ?string $status, int $limit, int $offset): array;
    public function count(string $customerId, ?string $status): int;
    public function resolve(string $id, string $customerId, string $resolverId, string $status): ?array;
    public function cancel(string $id, string $customerId, string $requesterId): ?array;
}
