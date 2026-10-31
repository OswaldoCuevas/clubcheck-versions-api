<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

interface IDatabaseConnection
{
    /** @return list<array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array;

    /** @return array<string, mixed>|null */
    public function fetchOne(string $sql, array $params = []): ?array;

    public function execute(string $sql, array $params = []): void;

    public function insert(string $table, array $data): int;

    public function update(string $table, array $data, string $where, array $whereParams = []): bool;

    public function delete(string $table, string $where, array $params = [], ?int $limit = 1): bool;

    public function begin(): void;

    public function commitTransaction(): void;

    public function rollbackTransaction(): void;
}
