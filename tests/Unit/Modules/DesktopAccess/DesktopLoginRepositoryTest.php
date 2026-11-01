<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\DesktopAccess;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\DesktopAccess\Repositories\DesktopLoginRepository;
use PHPUnit\Framework\TestCase;

final class DesktopLoginDatabaseFake implements IDatabaseConnection
{
    /** @var list<array<string, mixed>|null> */
    public array $oneRows = [];

    /** @var list<array{sql: string, params: array}> */
    public array $queries = [];

    /** @var list<array{table: string, data: array}> */
    public array $insertions = [];

    public function fetchAll(string $sql, array $params = []): array
    {
        return [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return array_shift($this->oneRows);
    }

    public function execute(string $sql, array $params = []): void
    {
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

final class DesktopLoginRepositoryTest extends TestCase
{
    public function test_failed_attempts_are_scoped_by_login_code_and_window(): void
    {
        $database = new DesktopLoginDatabaseFake();
        $database->oneRows = [[
            'total' => '3',
            'firstAttemptAt' => '2026-10-31 10:00:00',
        ]];
        $repository = new DesktopLoginRepository($database);

        $summary = $repository->failedAttemptsSince(
            'owner',
            'gym-1',
            '2026-10-31 09:50:00',
        );

        self::assertSame(3, $summary['total']);
        self::assertSame('2026-10-31 10:00:00', $summary['firstAttemptAt']);
        self::assertSame(
            [
                'owner',
                'gym-1',
                '2026-10-31 09:50:00',
            ],
            $database->queries[0]['params'],
        );
    }

    public function test_successful_attempt_is_persisted_with_database_columns(): void
    {
        $database = new DesktopLoginDatabaseFake();
        $repository = new DesktopLoginRepository($database);

        $repository->recordAttempt([
            'loginIdentifier' => 'owner',
            'codeAccess' => 'gym-1',
            'customerId' => 'customer-1',
            'adminId' => 'admin-1',
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'Unit Test',
            'wasSuccessful' => true,
            'failureReason' => null,
            'createdAt' => '2026-10-31 10:00:00',
        ]);

        self::assertSame(
            'CustomerWebLoginAttempts',
            $database->insertions[0]['table'],
        );
        self::assertSame(1, $database->insertions[0]['data']['WasSuccessful']);
        self::assertSame('customer-1', $database->insertions[0]['data']['CustomerId']);
    }
}
