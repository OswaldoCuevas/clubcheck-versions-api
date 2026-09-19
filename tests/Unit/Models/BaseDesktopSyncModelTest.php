<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Models\BaseDesktopSyncModel;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/app/Models/BaseDesktopSyncModel.php';

final class BaseDesktopSyncModelTest extends TestCase
{
    public function testPullAlwaysFiltersByCustomerAndHidesRemovedByDefault(): void
    {
        $database = new DesktopSyncDatabaseFake();
        $database->fetchAllResult = [['Id' => 'row-1', 'CustomerApiId' => 'customer-a']];
        $model = new DesktopSyncModelForTest($database);

        $rows = $model->pull(' customer-a ');

        self::assertSame($database->fetchAllResult, $rows);
        self::assertStringContainsString('CustomerApiId = ?', $database->lastSql);
        self::assertStringContainsString('Removed = 0', $database->lastSql);
        self::assertSame(['customer-a'], $database->lastParams);
    }

    public function testPullCanExplicitlyIncludeRemovedRows(): void
    {
        $database = new DesktopSyncDatabaseFake();
        $model = new DesktopSyncModelForTest($database);

        $model->pull('customer-a', true);

        self::assertStringNotContainsString('Removed = 0', $database->lastSql);
    }

    public function testPushInsertForcesAuthenticatedCustomerAndNormalizesBooleans(): void
    {
        $database = new DesktopSyncDatabaseFake();
        $model = new DesktopSyncModelForTest($database);

        $result = $model->push('customer-authenticated', [[
            'Id' => 'row-new',
            'CustomerApiId' => 'customer-attacker',
            'Name' => 'Registro',
            'Active' => 'true',
        ]]);

        self::assertTrue($result[0]['success']);
        self::assertSame('customer-authenticated', $database->inserted[0]['data']['CustomerApiId']);
        self::assertSame(1, $database->inserted[0]['data']['Active']);
        self::assertSame(0, $database->inserted[0]['data']['Removed']);
    }

    public function testPushUpdateChecksAndWritesWithinAuthenticatedCustomerOnly(): void
    {
        $database = new DesktopSyncDatabaseFake();
        $database->existingIdsByCustomer['row-existing|customer-a'] = true;
        $model = new DesktopSyncModelForTest($database);

        $result = $model->push('customer-a', [[
            'Id' => 'row-existing',
            'CustomerApiId' => 'customer-b',
            'Name' => 'Actualizado',
            'Active' => false,
        ]]);

        self::assertTrue($result[0]['success']);
        self::assertSame(['row-existing', 'customer-a'], $database->lastFetchOneParams);
        self::assertSame('Id = ? AND CustomerApiId = ?', $database->updated[0]['where']);
        self::assertSame(['row-existing', 'customer-a'], $database->updated[0]['whereParams']);
        self::assertSame('customer-a', $database->updated[0]['data']['CustomerApiId']);
    }

    public function testInvalidRecordDoesNotWrite(): void
    {
        $database = new DesktopSyncDatabaseFake();
        $model = new DesktopSyncModelForTest($database);

        $result = $model->push('customer-a', [['Name' => 'Sin id']]);

        self::assertFalse($result[0]['success']);
        self::assertSame([], $database->inserted);
        self::assertSame([], $database->updated);
    }
}

final class DesktopSyncModelForTest extends BaseDesktopSyncModel
{
    protected string $table = 'TestDesktopRows';
    protected string $primaryKey = 'Id';
    protected array $columns = ['Id', 'CustomerApiId', 'Name', 'Active', 'Removed'];
    protected array $nullableColumns = [];
    protected array $booleanColumns = ['Active', 'Removed'];
    protected bool $autoIncrement = false;
    protected ?string $orderBy = 'Name';
    protected ?string $softDeleteColumn = 'Removed';

    public function __construct(DesktopSyncDatabaseFake $database)
    {
        $this->db = $database;
    }
}

final class DesktopSyncDatabaseFake
{
    public array $fetchAllResult = [];
    public array $existingIdsByCustomer = [];
    public string $lastSql = '';
    public array $lastParams = [];
    public array $lastFetchOneParams = [];
    public array $inserted = [];
    public array $updated = [];

    public function fetchAll(string $sql, array $params): array
    {
        $this->lastSql = $sql;
        $this->lastParams = $params;
        return $this->fetchAllResult;
    }

    public function fetchOne(string $sql, array $params): ?array
    {
        $this->lastSql = $sql;
        $this->lastFetchOneParams = $params;
        return isset($this->existingIdsByCustomer[$params[0] . '|' . $params[1]]) ? ['Id' => $params[0]] : null;
    }

    public function insert(string $table, array $data): int
    {
        $this->inserted[] = compact('table', 'data');
        return 1;
    }

    public function update(string $table, array $data, string $where, array $whereParams): bool
    {
        $this->updated[] = compact('table', 'data', 'where', 'whereParams');
        return true;
    }
}
