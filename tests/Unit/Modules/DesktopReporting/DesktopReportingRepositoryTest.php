<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\DesktopReporting;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\DesktopReporting\Repositories\DesktopReportingRepository;
use App\Services\MembershipSalesService;
use App\Services\UserPhotoService;
use PHPUnit\Framework\TestCase;

final class DesktopReportingDatabaseFake implements IDatabaseConnection
{
    /** @var list<array{sql: string, params: array}> */
    public array $queries = [];

    public function fetchAll(string $sql, array $params = []): array
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return ['value' => 0];
    }

    public function execute(string $sql, array $params = []): void
    {
    }

    public function insert(string $table, array $data): int
    {
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

final class DesktopReportingRepositoryTest extends TestCase
{
    public function test_dashboard_scopes_every_database_query_to_the_customer(): void
    {
        $database = new DesktopReportingDatabaseFake();
        $repository = new DesktopReportingRepository(
            $database,
            new MembershipSalesService($database),
            new UserPhotoService($database),
        );

        $dashboard = $repository->dashboard(
            'customer-1',
            ['range' => 'week'],
        );

        self::assertSame('customer-1', $dashboard['customerApiId']);
        self::assertNotEmpty($database->queries);

        foreach ($database->queries as $query) {
            self::assertStringContainsString('CustomerApiId', $query['sql']);
            self::assertSame('customer-1', $query['params'][0]);
        }
    }
}
