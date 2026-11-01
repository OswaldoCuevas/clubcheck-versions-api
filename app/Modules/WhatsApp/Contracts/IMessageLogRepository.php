<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Contracts;

interface IMessageLogRepository
{
    public function byCustomer(string $customerId, int $limit, int $offset): array;

    public function find(string $id): ?array;

    public function create(array $record): bool;

    public function update(string $id, array $record): bool;

    public function delete(string $id): bool;
}
