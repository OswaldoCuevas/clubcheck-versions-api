<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Customers;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Customers\Repositories\DesktopBulkCatalog;
use App\Modules\Customers\Repositories\DesktopSyncRepository;
use PHPUnit\Framework\TestCase;

final class DesktopSyncDatabaseFake implements IDatabaseConnection
{
    /** @var list<list<array<string, mixed>>> */
    public array $allRows = [];

    /** @var list<array<string, mixed>|null> */
    public array $oneRows = [];

    /** @var list<array{sql: string, params: array}> */
    public array $allQueries = [];

    /** @var list<array{sql: string, params: array}> */
    public array $oneQueries = [];

    /** @var list<array{table: string, data: array}> */
    public array $insertions = [];

    /** @var list<array{table: string, data: array, where: string, params: array}> */
    public array $updates = [];

    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;

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
        $this->begins++;
    }

    public function commitTransaction(): void
    {
        $this->commits++;
    }

    public function rollbackTransaction(): void
    {
        $this->rollbacks++;
    }
}

final class DesktopSyncRepositoryTest extends TestCase
{
    public function test_catalog_preserves_the_legacy_bulk_order(): void
    {
        $names = (new DesktopBulkCatalog())->names();

        self::assertCount(35, $names);
        self::assertSame('users', $names[0]);
        self::assertSame('barcodeLookupCache', $names[34]);
    }

    public function test_pull_applies_customer_soft_delete_and_ordering(): void
    {
        $database = new DesktopSyncDatabaseFake();
        $database->allRows = [[['Id' => 'product-1']]];
        $repository = new DesktopSyncRepository($database, new DesktopBulkCatalog());

        $rows = $repository->pull('products', 'customer-1', false);

        self::assertSame([['Id' => 'product-1']], $rows);
        self::assertStringContainsString(
            'CustomerApiId = ? AND IsDeleted = 0 ORDER BY Name ASC',
            $database->allQueries[0]['sql'],
        );
        self::assertSame(['customer-1'], $database->allQueries[0]['params']);
    }

    public function test_generic_push_inserts_only_allowed_columns_and_forces_customer(): void
    {
        $database = new DesktopSyncDatabaseFake();
        $database->oneRows = [null];
        $repository = new DesktopSyncRepository($database, new DesktopBulkCatalog());

        $result = $repository->push('products', 'customer-1', [[
            'Id' => 'product-1',
            'Name' => 'Producto',
            'Active' => true,
            'CustomerApiId' => 'different-customer',
            'Unexpected' => 'ignored',
        ]]);

        self::assertSame([[
            'id' => 'product-1',
            'success' => true,
        ]], $result);
        self::assertSame('ProductDesktop', $database->insertions[0]['table']);
        self::assertSame(
            [
                'Id' => 'product-1',
                'Name' => 'Producto',
                'Active' => 1,
                'CustomerApiId' => 'customer-1',
                'IsDeleted' => 0,
            ],
            $database->insertions[0]['data'],
        );
    }

    public function test_user_push_rejects_a_record_owned_by_another_customer(): void
    {
        $database = new DesktopSyncDatabaseFake();
        $database->oneRows = [['CustomerApiId' => 'other-customer']];
        $repository = new DesktopSyncRepository($database, new DesktopBulkCatalog());

        $result = $repository->push('users', 'customer-1', [[
            'Id' => 'user-1',
            'Fullname' => 'Socio',
        ]]);

        self::assertSame([[
            'id' => 'user-1',
            'success' => false,
            'messageError' => 'No se pudo sincronizar el socio',
        ]], $result);
        self::assertSame(1, $database->begins);
        self::assertSame(0, $database->commits);
        self::assertSame(1, $database->rollbacks);
        self::assertSame([], $database->updates);
        self::assertSame([], $database->insertions);
    }

    public function test_user_pull_adds_optional_photo_metadata(): void
    {
        $database = new DesktopSyncDatabaseFake();
        $database->allRows = [
            [[
                'Id' => 'user-1',
                'Fullname' => 'Socio',
            ]],
            [[
                'UserId' => 'user-1',
                'Version' => '3',
                'UpdatedAt' => '2026-10-31 12:00:00',
            ]],
        ];
        $repository = new DesktopSyncRepository($database, new DesktopBulkCatalog());

        $rows = $repository->pull('users', 'customer-1', false);

        self::assertSame(
            [
                'version' => 3,
                'updatedAt' => '2026-10-31 12:00:00',
            ],
            $rows[0]['photo'],
        );
        self::assertSame(
            ['customer-1', 'user-1'],
            $database->allQueries[1]['params'],
        );
    }
}
