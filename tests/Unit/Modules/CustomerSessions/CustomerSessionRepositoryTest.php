<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CustomerSessions;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\CustomerSessions\Repositories\CustomerSessionRepository;
use PHPUnit\Framework\TestCase;

final class CustomerSessionDatabaseFake implements IDatabaseConnection
{
    /** @var list<array<string, mixed>|null> */
    public array $oneRows = [];

    /** @var list<list<array<string, mixed>>> */
    public array $allRows = [];

    /** @var list<array{sql: string, params: array}> */
    public array $executions = [];

    /** @var list<array{sql: string, params: array}> */
    public array $oneQueries = [];

    /** @var list<array{sql: string, params: array}> */
    public array $allQueries = [];

    /** @var list<array{table: string, data: array}> */
    public array $insertions = [];

    /** @var list<array{table: string, data: array, where: string, params: array}> */
    public array $updates = [];

    public function fetchAll(string $sql, array $params = []): array
    {
        $this->allQueries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return array_shift($this->allRows) ?? [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->oneQueries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

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
    ): bool
    {
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
    ): bool
    {
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

final class CustomerSessionRepositoryTest extends TestCase
{
    public function test_find_active_scopes_the_query_by_customer_device_and_grace(): void
    {
        $database = new CustomerSessionDatabaseFake();
        $database->oneRows = [null];
        $repository = new CustomerSessionRepository($database);

        $session = $repository->findActive(
            'customer-1',
            'device-1',
            180,
        );

        self::assertNull($session);
        self::assertStringContainsString(
            'CustomerId = ? AND Status = ? AND DeviceId = ?',
            $database->oneQueries[0]['sql'],
        );
        self::assertSame(
            [
                'customer-1',
                'active',
                'device-1',
                180,
            ],
            $database->oneQueries[0]['params'],
        );
    }

    public function test_start_persists_the_session_and_updates_customer_last_seen(): void
    {
        $database = new CustomerSessionDatabaseFake();
        $database->oneRows = [null];
        $repository = new CustomerSessionRepository($database);

        $session = $repository->start(
            [
                'customerId' => 'customer-1',
                'deviceId' => 'device-1',
                'appVersion' => '1.2.3',
                'metadata' => ['source' => 'unit'],
                'ipAddress' => '127.0.0.1',
            ],
            180,
        );

        self::assertSame('CustomerSessions', $database->insertions[0]['table']);
        self::assertSame('customer-1', $database->insertions[0]['data']['CustomerId']);
        self::assertSame('{"source":"unit"}', $database->insertions[0]['data']['Metadata']);
        self::assertSame('Customers', $database->updates[0]['table']);
        self::assertSame('customer-1', $database->updates[0]['params'][0]);
        self::assertSame('active', $session['status']);
        self::assertSame('customer-1', $session['customerId']);
    }

    public function test_list_filters_by_customer_and_hydrates_metadata_and_timestamps(): void
    {
        $database = new CustomerSessionDatabaseFake();
        $database->allRows = [[
            [
                'Id' => 'session-1',
                'CustomerId' => 'customer-1',
                'DeviceId' => 'device-1',
                'AppVersion' => '1.2.3',
                'IpAddress' => '127.0.0.1',
                'Metadata' => '{"state":"running"}',
                'Status' => 'active',
                'StartedAt' => '2026-10-31 10:00:00',
                'LastSeen' => '2026-10-31 10:01:00',
                'EndedAt' => null,
                'EndedReason' => null,
            ],
        ]];
        $repository = new CustomerSessionRepository($database);

        $sessions = $repository->list('customer-1', 180);

        self::assertSame(['customer-1'], $database->allQueries[0]['params']);
        self::assertStringContainsString(
            'WHERE CustomerId = ? ORDER BY LastSeen DESC',
            $database->allQueries[0]['sql'],
        );
        self::assertSame(['state' => 'running'], $sessions[0]['metadata']);
        self::assertIsInt($sessions[0]['lastSeen']);
    }

    public function test_purge_expired_uses_the_configured_grace_period(): void
    {
        $database = new CustomerSessionDatabaseFake();
        $repository = new CustomerSessionRepository($database);

        $repository->purgeExpired(240);

        self::assertSame([240], $database->executions[0]['params']);
        self::assertStringContainsString(
            'LastSeen < DATE_SUB(NOW(), INTERVAL ? SECOND)',
            $database->executions[0]['sql'],
        );
    }
}
