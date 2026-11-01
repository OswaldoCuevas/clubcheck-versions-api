<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Downloads\Repositories\DownloadRepository;
use App\Modules\Email\Repositories\EmailRepository;
use App\Modules\Isapi\Repositories\IsapiCommandRepository;
use App\Modules\WhatsApp\Repositories\MessageLogRepository;
use PHPUnit\Framework\TestCase;

final class RemainingControllerRepositoriesTest extends TestCase
{
    public function testEmailTypesAreReadByTheEmailRepository(): void
    {
        $database = new RemainingControllerDatabaseFake();

        (new EmailRepository($database))->activeTypes();

        self::assertStringContainsString('FROM EmailTypes', $database->queries[0]['sql']);
        self::assertStringContainsString('IsActive = 1', $database->queries[0]['sql']);
    }

    public function testIsapiHistoryIsScopedToRequestedCustomer(): void
    {
        $database = new RemainingControllerDatabaseFake();

        (new IsapiCommandRepository($database))->recent('customer-1', 25);

        self::assertStringContainsString('c.CustomerId = ?', $database->queries[0]['sql']);
        self::assertSame(['customer-1', 25], $database->queries[0]['params']);
    }

    public function testMessageLogListExcludesDebugMessages(): void
    {
        $database = new RemainingControllerDatabaseFake();

        (new MessageLogRepository($database))->byCustomer('customer-1', 50, 10);

        self::assertStringContainsString('IsDebug = 0', $database->queries[0]['sql']);
        self::assertSame(['customer-1', 50, 10], $database->queries[0]['params']);
    }

    public function testReleaseVersionUsesParameterizedUpsert(): void
    {
        $database = new RemainingControllerDatabaseFake();
        $version = [
            'name' => '1.2.3.4',
            'url' => 'https://example.test/app.exe',
            'sha256' => 'exe-hash',
            'setupUrl' => 'https://example.test/setup.zip',
            'setupSha256' => 'setup-hash',
            'setupFileSize' => 100,
            'mandatory' => true,
            'releaseNotes' => 'Notes',
            'uploadDate' => '2026-11-01 12:00:00',
        ];

        $saved = (new DownloadRepository($database))->saveVersion($version);

        self::assertTrue($saved);
        self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $database->executions[0]['sql']);
        self::assertSame('1.2.3.4', $database->executions[0]['params'][0]);
    }
}

final class RemainingControllerDatabaseFake implements IDatabaseConnection
{
    public array $queries = [];
    public array $executions = [];

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

        return null;
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
