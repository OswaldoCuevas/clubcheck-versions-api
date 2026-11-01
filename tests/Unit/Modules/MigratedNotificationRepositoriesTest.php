<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Billing\Repositories\BillingCustomerRepository;
use App\Modules\Notifications\Repositories\NotificationRepository;
use App\Modules\PermissionRequests\Repositories\PermissionRequestRepository;
use App\Modules\PushNotifications\Repositories\PushTokenRepository;
use App\Modules\UserPhotos\Repositories\UserPhotoRepository;
use PHPUnit\Framework\TestCase;

final class NotificationRepositoryDatabase implements IDatabaseConnection
{
    public array $allRows = [];
    public ?array $oneRow = null;
    public array $executions = [];
    public array $queries = [];
    public array $insertions = [];
    public array $deletions = [];

    public function fetchAll(string $sql, array $params = []): array
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return $this->allRows;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return $this->oneRow;
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
        return true;
    }

    public function delete(
        string $table,
        string $where,
        array $params = [],
        ?int $limit = 1,
    ): bool {
        $this->deletions[] = [
            'table' => $table,
            'where' => $where,
            'params' => $params,
        ];

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

final class MigratedNotificationRepositoriesTest extends TestCase
{
    public function testNotificationRepositoryScopesUnreadCountToAdministrator(): void
    {
        $database = new NotificationRepositoryDatabase();
        $database->oneRow = ['Total' => 4];

        $count = (new NotificationRepository($database))->unreadCount(
            'customer-1',
            'administrator-1',
        );

        self::assertSame(4, $count);
        self::assertStringContainsString('CustomerId = ?', $database->queries[0]['sql']);
        self::assertStringContainsString('RecipientAdminId = ?', $database->queries[0]['sql']);
        self::assertSame(
            ['customer-1', 'administrator-1'],
            $database->queries[0]['params'],
        );
    }

    public function testUserPhotoRepositoryScopesReadsToCustomerAndActiveUser(): void
    {
        $database = new NotificationRepositoryDatabase();
        $database->oneRow = [
            'StorageKey' => str_repeat('a', 64),
            'Mime' => 'image/png',
        ];

        $photo = (new UserPhotoRepository($database))->findAccessible(
            'customer-1',
            'user-1',
        );

        self::assertSame('image/png', $photo['Mime']);
        self::assertStringContainsString(
            'COALESCE(u.Removed, 0) = 0',
            $database->queries[0]['sql'],
        );
        self::assertSame(
            ['user-1', 'customer-1'],
            $database->queries[0]['params'],
        );
    }

    public function testPushRepositoryHashesTheTokenBeforePersistingIt(): void
    {
        $database = new NotificationRepositoryDatabase();

        (new PushTokenRepository($database))->register(
            'customer-1',
            'secret-device-token',
            'desktop',
        );

        self::assertCount(1, $database->executions);
        self::assertSame('customer-1', $database->executions[0]['params'][1]);
        self::assertSame(
            hash('sha256', 'secret-device-token'),
            $database->executions[0]['params'][3],
        );
    }

    public function testPermissionRepositoryAppliesCustomerStatusAndPagination(): void
    {
        $database = new NotificationRepositoryDatabase();

        (new PermissionRequestRepository($database))->list(
            'customer-1',
            'Pending',
            30,
            60,
        );

        self::assertStringContainsString(
            'pr.CustomerId = ?',
            $database->queries[0]['sql'],
        );
        self::assertSame(
            ['customer-1', 'Pending', 30, 60],
            $database->queries[0]['params'],
        );
    }

    public function testBillingCustomerRepositoryMapsThePersistenceRow(): void
    {
        $database = new NotificationRepositoryDatabase();
        $database->oneRow = [
            'Id' => 'customer-1',
            'Name' => 'Club Uno',
            'Email' => 'club@example.test',
            'Phone' => '555',
            'BillingId' => 'cus_123',
            'Token' => 'machine-token',
            'DeviceName' => 'DESKTOP',
            'IsActive' => 1,
            'CodeAccess' => 'ABC',
            'TokenJwt' => 'jwt',
        ];

        $customer = (new BillingCustomerRepository($database))->customer('customer-1');

        self::assertSame('cus_123', $customer['billingId']);
        self::assertSame('ABC', $customer['codeAccess']);
        self::assertTrue($customer['isActive']);
    }

}
