<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\AdminAccess;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\AdminAccess\Repositories\AdminAuthRepository;
use PHPUnit\Framework\TestCase;

final class AdminAuthDatabaseFake implements IDatabaseConnection
{
    /** @var list<list<array<string, mixed>>> */
    public array $allRows = [];

    /** @var list<array<string, mixed>|null> */
    public array $oneRows = [];

    /** @var list<array{sql: string, params: array}> */
    public array $executions = [];

    /** @var list<array{table: string, data: array}> */
    public array $insertions = [];

    /** @var list<array{table: string, data: array, where: string, params: array}> */
    public array $updates = [];

    public function fetchAll(string $sql, array $params = []): array
    {
        return array_shift($this->allRows) ?? [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        return array_shift($this->oneRows);
    }

    public function execute(string $sql, array $params = []): void
    {
        $this->executions[] = [
            'sql' => $sql,
            'params' => $params,
        ];
    }

    public function insert(string $table, array $data): int
    {
        $this->insertions[] = [
            'table' => $table,
            'data' => $data,
        ];

        return 1;
    }

    public function update(
        string $table,
        array $data,
        string $where,
        array $whereParams = [],
    ): bool {
        $this->updates[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'params' => $whereParams,
        ];

        return true;
    }

    public function delete(
        string $table,
        string $where,
        array $params = [],
        ?int $limit = 1,
    ): bool {
        return true;
    }

    public function begin(): void
    {
    }

    public function commitTransaction(): void
    {
    }

    public function rollbackTransaction(): void
    {
    }
}

final class AdminAuthRepositoryTest extends TestCase
{
    public function test_roles_and_permissions_are_mapped_to_string_lists(): void
    {
        $database = new AdminAuthDatabaseFake();
        $database->allRows = [
            [
                ['Name' => 'administrator'],
                ['Name' => 'administrator'],
            ],
            [
                ['Name' => 'admin_access'],
                ['Name' => 'customers_manage'],
            ],
        ];
        $repository = new AdminAuthRepository($database);

        self::assertSame(['administrator'], $repository->roles(1));
        self::assertSame(
            ['admin_access', 'customers_manage'],
            $repository->permissions(1),
        );
    }

    public function test_attempt_and_session_updates_use_persistence_contract(): void
    {
        $database = new AdminAuthDatabaseFake();
        $repository = new AdminAuthRepository($database);

        $repository->recordAttempt(
            'admin',
            false,
            '127.0.0.1',
            'Unit Test',
        );
        $repository->clearFailedAttempts('admin');
        $repository->saveRememberToken(1, 'remember-token');

        self::assertSame('LoginAttempts', $database->insertions[0]['table']);
        self::assertSame(0, $database->insertions[0]['data']['WasSuccessful']);
        self::assertSame(['admin'], $database->executions[0]['params']);
        self::assertSame('remember-token', $database->updates[0]['data']['RememberToken']);
    }
}
