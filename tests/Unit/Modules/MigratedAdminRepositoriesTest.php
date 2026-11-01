<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\CustomerAccess\Repositories\CustomerAccessRepository;
use App\Modules\Customers\Repositories\CustomerRepository;
use App\Modules\CustomerStats\Repositories\CustomerStatsRepository;
use App\Modules\Downloads\Repositories\DownloadRepository;
use App\Modules\Licenses\Repositories\LicenseRepository;
use App\Modules\WhatsApp\Repositories\WhatsAppAdminRepository;
use PHPUnit\Framework\TestCase;

final class ModuleRepositoryDatabaseFake implements IDatabaseConnection
{
    public array $one = [];
    public array $all = [];

    public function fetchAll(string $sql, array $params = []): array
    {
        return array_shift($this->all) ?? [];
    }
    public function fetchOne(string $sql, array $params = []): ?array
    {
        return array_shift($this->one);
    }
    public function execute(string $sql, array $params = []): void
    {
    }
    public function insert(string $table, array $data): int
    {
        return 1;
    }
    public function update(string $table, array $data, string $where, array $whereParams = []): bool
    {
        return true;
    }
    public function delete(string $table, string $where, array $params = [], ?int $limit = 1): bool
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

final class MigratedAdminRepositoriesTest extends TestCase
{
    public function test_customer_repository_maps_database_rows(): void
    {
        $db = new ModuleRepositoryDatabaseFake();
        $db->one = [[
            'Id' => 'c1',
            'Name' => 'Club',
            'Email' => 'a@b.test',
            'Phone' => null,
            'DeviceName' => null,
            'Token' => null,
            'IsActive' => 1,
        ]];
        $customer = (new CustomerRepository($db))->find('c1');
        self::assertSame('c1', $customer['customerId']);
        self::assertSame('Club', $customer['name']);
        self::assertTrue($customer['isActive']);
    }
    public function test_access_repository_maps_login_summary(): void
    {
        $db = new ModuleRepositoryDatabaseFake();
        $db->one = [[
            'total' => '4',
            'successful' => '3',
            'failed' => '1',
            'failedLast10Minutes' => '1',
            'activeFailedCombinations' => '1',
        ]];
        $summary = (new CustomerAccessRepository($db))->loginSummary();
        self::assertSame(
            [
                'total' => 4,
                'successful' => 3,
                'failed' => 1,
                'failedLast10Minutes' => 1,
                'activeFailedCombinations' => 1,
            ],
            $summary,
        );
    }
    public function test_download_repository_builds_pagination(): void
    {
        $db = new ModuleRepositoryDatabaseFake();
        $db->one = [['Total' => '2']];
        $db->all = [[['IpAddress' => '127.0.0.1']]];
        $result = (new DownloadRepository($db))->grouped(1, 20, null);
        self::assertSame(2, $result['total']);
        self::assertSame(1, $result['totalPages']);
        self::assertCount(1, $result['data']);
    }
    public function test_stats_repository_builds_global_read_model(): void
    {
        $db = new ModuleRepositoryDatabaseFake();
        $db->one = array_fill(0, 9, ['total' => '2']);
        $stats = (new CustomerStatsRepository($db))->global();
        self::assertSame(2, $stats['totalCustomers']);
        self::assertSame(2, $stats['monthlyMessages']);
    }
    public function test_license_repository_maps_rows(): void
    {
        $db = new ModuleRepositoryDatabaseFake();
        $db->all = [[[
            'Id' => '7',
            'CustomerId' => 'c1',
            'BillingId' => null,
            'CustomerName' => 'Club',
            'CustomerEmail' => null,
            'PlanLookupKey' => 'free',
            'PlanName' => 'Start',
            'IsPermanent' => 0,
            'ExpiresAt' => null,
            'MachineToken' => null,
            'LicenseToken' => 'token',
            'CreatedBy' => 'admin',
            'AdminUsername' => 'admin',
            'IssuedAt' => '2026-01-01 00:00:00',
        ]]];
        $rows = (new LicenseRepository($db))->all();
        self::assertSame(7, $rows[0]['id']);
        self::assertFalse($rows[0]['isPermanent']);
    }
    public function test_whatsapp_repository_returns_configuration_rows(): void
    {
        $db = new ModuleRepositoryDatabaseFake();
        $db->all = [[['Id' => 'w1']]];
        self::assertSame([['Id' => 'w1']], (new WhatsAppAdminRepository($db))->configurations());
    }
    public function test_migrated_handlers_do_not_reference_models_or_database_connection(): void
    {
        $root = dirname(__DIR__, 3) . '/app/Modules';
        $modules = [
            'AdminAccess',
            'Admin',
            'ApiDocs',
            'Customers',
            'CustomerAccess',
            'CustomerStats',
            'Downloads',
            'Licenses',
            'WhatsApp',
        ];

        foreach ($modules as $module) {
            foreach (glob("{$root}/{$module}/Features/*/Handler.php") ?: [] as $handler) {
                $source = file_get_contents($handler);
                self::assertStringNotContainsString('Models\\', $source, $handler);
                self::assertStringNotContainsString('IDatabaseConnection', $source, $handler);
            }
        }
    }
}
