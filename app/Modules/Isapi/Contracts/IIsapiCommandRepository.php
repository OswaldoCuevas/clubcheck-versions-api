<?php

declare(strict_types=1);

namespace App\Modules\Isapi\Contracts;

interface IIsapiCommandRepository
{
    public function heartbeat(string $customerId, array $data): array;

    public function create(
        string $customerId,
        string $agentId,
        int $terminalIndex,
        ?string $deviceId,
        array $definition,
        ?string $requestedBy,
    ): array;

    public function claimNext(string $customerId, string $agentId): ?array;

    public function complete(string $id, string $customerId, array $result): ?array;

    public function recent(?string $customerId, int $limit): array;

    public function agents(): array;

    public function find(string $id): ?array;

    public function findForCustomer(string $id, string $customerId): ?array;
}
