<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WhatsApp;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\WhatsApp\Contracts\IWhatsAppCustomerRepository;
use App\Modules\WhatsApp\Contracts\IWhatsAppGateway;
use App\Modules\WhatsApp\Contracts\IWhatsAppPermitChecker;
use App\Modules\WhatsApp\Repositories\WhatsAppCustomerRepository;
use App\Modules\WhatsApp\Services\WhatsAppCustomerOperations;
use PHPUnit\Framework\TestCase;

final class WhatsAppCustomerModuleTest extends TestCase
{
    public function testMonthlyCountUsesAuthenticatedCustomerAndRepository(): void
    {
        $repository = new WhatsAppCustomerRepositoryFake();
        $repository->monthlyCount = 7;
        $operations = new WhatsAppCustomerOperations(
            $repository,
            new WhatsAppGatewayFake(),
            new WhatsAppPermitCheckerFake(),
        );

        $result = $operations->monthlyCount('authenticated', 'requested');

        self::assertSame('authenticated', $repository->lastCustomerId);
        self::assertSame('authenticated', $result['customerApiId']);
        self::assertSame(7, $result['count']);
    }

    public function testEmptyBulkReturnsLocallyWithoutCallingGateway(): void
    {
        $gateway = new WhatsAppGatewayFake();
        $operations = new WhatsAppCustomerOperations(
            new WhatsAppCustomerRepositoryFake(),
            $gateway,
            new WhatsAppPermitCheckerFake(),
        );

        $result = $operations->sendBulk('customer-1', ['items' => []]);

        self::assertFalse($gateway->bulkCalled);
        self::assertSame(0, $result['total']);
        self::assertSame([], $result['success']);
        self::assertSame([], $result['failed']);
    }

    public function testRepositoryScopesMonthlyCountToCustomer(): void
    {
        $database = new WhatsAppDatabaseFake();
        $database->oneRow = ['total' => 3];
        $repository = new WhatsAppCustomerRepository($database);

        $count = $repository->monthlySuccessfulCount('customer-7');

        self::assertSame(3, $count);
        self::assertStringContainsString('IsDebug = 0', $database->queries[0]['sql']);
        self::assertSame(['customer-7'], $database->queries[0]['params']);
    }
}

final class WhatsAppCustomerRepositoryFake implements IWhatsAppCustomerRepository
{
    public int $monthlyCount = 0;
    public ?string $lastCustomerId = null;

    public function monthlySuccessfulCount(string $customerId): int
    {
        $this->lastCustomerId = $customerId;

        return $this->monthlyCount;
    }

    public function searchMessages(
        string $customerId,
        array $filters,
        int $page,
        int $perPage,
    ): array {
        return [
            'data' => [],
            'pagination' => [],
        ];
    }

    public function configurationByCustomer(string $customerId): ?array
    {
        return null;
    }

    public function configurationById(string $id): ?array
    {
        return null;
    }

    public function upsertConfiguration(array $data): array
    {
        return [
            'success' => true,
            'id' => 'configuration-1',
            'created' => true,
            'error' => null,
        ];
    }
}

final class WhatsAppGatewayFake implements IWhatsAppGateway
{
    public bool $bulkCalled = false;

    public function isConfigured(?string $customerId = null): bool
    {
        return false;
    }

    public function sendSubscription(
        string $customerId,
        array $payload,
        ?array $limitDebug,
        ?string $permitError,
    ): array {
        return [];
    }

    public function sendWarning(
        string $customerId,
        array $payload,
        ?array $limitDebug,
        ?string $permitError,
    ): array {
        return [];
    }

    public function sendFinalized(
        string $customerId,
        array $payload,
        ?array $limitDebug,
        ?string $permitError,
    ): array {
        return [];
    }

    public function sendLastDay(
        string $customerId,
        array $payload,
        ?array $limitDebug,
        ?string $permitError,
    ): array {
        return [];
    }

    public function sendBulk(string $customerId, array $items): array
    {
        $this->bulkCalled = true;

        return [];
    }

    public function registerBusinessProfile(
        string $customerId,
        string $businessName,
        ?string $logoPath,
        array $additionalData,
    ): array {
        return [
            'success' => true,
            'error' => null,
        ];
    }
}

final class WhatsAppPermitCheckerFake implements IWhatsAppPermitChecker
{
    public function check(string $customerId): array
    {
        return [
            'error' => null,
            'debug' => null,
        ];
    }
}

final class WhatsAppDatabaseFake implements IDatabaseConnection
{
    public ?array $oneRow = null;
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

        return $this->oneRow;
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
