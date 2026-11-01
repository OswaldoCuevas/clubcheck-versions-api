<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Downloads;

use App\Exceptions\ApiException;
use App\Modules\Downloads\Contracts\IDownloadRepository;
use App\Modules\Downloads\Services\DownloadFileLocator;
use App\Modules\Downloads\Services\PublicDownloadService;
use PHPUnit\Framework\TestCase;

final class DownloadRepositoryFake implements IDownloadRepository
{
    /** @var array<string, mixed> */
    public array $version = [
        'latestVersion' => '2.0.0',
        'url' => 'https://example.test/ClubCheck.exe',
        'sha256' => 'hash',
        'setupUrl' => null,
        'setupSha256' => null,
        'setupFileSize' => null,
        'mandatory' => false,
        'releaseNotes' => 'Release',
        'uploadDate' => '2026-10-31 12:00:00',
        'timestamp' => '2026-10-31 12:00:00',
    ];

    /** @var list<array<string, mixed>> */
    public array $recordedDownloads = [];

    public function latestVersion(): array
    {
        return $this->version;
    }

    public function recordDownload(array $download): void
    {
        $this->recordedDownloads[] = $download;
    }

    public function grouped(int $page, int $perPage, ?string $ip): array
    {
        return [];
    }

    public function summary(): array
    {
        return [];
    }

    public function byIp(string $ip, int $limit = 100): array
    {
        return [];
    }
}

final class PublicDownloadServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'clubcheck-download-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            unlink($path);
        }

        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_version_adds_public_urls_and_executable_metadata(): void
    {
        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . 'ClubCheck-2.0.0.exe',
            'binary',
        );
        $service = $this->service(new DownloadRepositoryFake());

        $version = $service->version('https://clubcheck.example.test');

        self::assertTrue($version['hasUpdate']);
        self::assertTrue($version['fileExists']);
        self::assertSame(6, $version['fileSize']);
        self::assertSame(
            'https://clubcheck.example.test/api/download',
            $version['downloadUrl'],
        );
    }

    public function test_check_update_compares_versions_and_returns_download_urls(): void
    {
        $service = $this->service(new DownloadRepositoryFake());

        $result = $service->checkUpdate(
            ['version' => '1.9.0'],
            'https://clubcheck.example.test',
        );

        self::assertTrue($result['hasUpdate']);
        self::assertSame('2.0.0', $result['serverVersion']);
        self::assertSame('1.9.0', $result['clientVersion']);
        self::assertSame(
            'https://clubcheck.example.test/api/download-setup',
            $result['downloadSetupUrl'],
        );
    }

    public function test_setup_without_a_configured_file_preserves_not_found_body(): void
    {
        $service = $this->service(new DownloadRepositoryFake());

        try {
            $service->downloadSetup([]);
            self::fail('Expected setup download to fail.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame(
                'Archivo Setup no encontrado',
                $exception->toArray()['error'],
            );
        }
    }

    public function test_binary_download_records_request_metadata(): void
    {
        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . 'ClubCheck-2.0.0.exe',
            'binary',
        );
        $repository = new DownloadRepositoryFake();
        $service = $this->service($repository);

        $result = $service->downloadExecutable([
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'Unit Test',
            'referrer' => 'https://example.test',
        ]);

        self::assertNull($result->information);
        self::assertSame('exe', $repository->recordedDownloads[0]['type']);
        self::assertSame(
            '127.0.0.1',
            $repository->recordedDownloads[0]['ipAddress'],
        );
        self::assertSame(6, $repository->recordedDownloads[0]['fileSize']);
    }

    private function service(
        DownloadRepositoryFake $repository,
    ): PublicDownloadService {
        return new PublicDownloadService(
            $repository,
            new DownloadFileLocator($this->directory),
        );
    }
}
