<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Downloads;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Downloads\Repositories\DownloadRepository;
use PHPUnit\Framework\TestCase;

final class DownloadDatabaseFake implements IDatabaseConnection
{
    /** @var list<array<string, mixed>|null> */
    public array $oneRows = [];

    /** @var list<array{table: string, data: array}> */
    public array $insertions = [];

    public function fetchAll(string $sql, array $params = []): array
    {
        return [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
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

final class DownloadRepositoryTest extends TestCase
{
    public function test_latest_version_maps_database_columns_to_public_fields(): void
    {
        $database = new DownloadDatabaseFake();
        $database->oneRows = [[
            'Id' => 1,
            'Name' => '2.0.0',
            'Url' => 'https://example.test/application.exe',
            'Sha256' => 'application-hash',
            'SetupUrl' => 'https://example.test/setup.zip',
            'SetupSha256' => 'setup-hash',
            'SetupFileSize' => 100,
            'IsMandatory' => 1,
            'ReleaseNotes' => 'Release',
            'UploadDate' => '2026-10-31 12:00:00',
        ]];

        $version = (new DownloadRepository($database))->latestVersion();

        self::assertSame('2.0.0', $version['latestVersion']);
        self::assertTrue($version['mandatory']);
        self::assertSame('setup-hash', $version['setupSha256']);
        self::assertSame($version['uploadDate'], $version['timestamp']);
    }

    public function test_record_download_persists_request_and_file_metadata(): void
    {
        $database = new DownloadDatabaseFake();
        $repository = new DownloadRepository($database);

        $repository->recordDownload([
            'type' => 'exe',
            'version' => '2.0.0',
            'filename' => 'ClubCheck-2.0.0.exe',
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'Unit Test',
            'referrer' => null,
            'fileSize' => 100,
        ]);

        self::assertSame('DownloadLogs', $database->insertions[0]['table']);
        self::assertSame('exe', $database->insertions[0]['data']['DownloadType']);
        self::assertSame('127.0.0.1', $database->insertions[0]['data']['IpAddress']);
    }
}
