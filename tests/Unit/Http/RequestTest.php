<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Request;
use Core\UrlHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class RequestTest extends TestCase
{
    private array $originalServer;
    private ReflectionProperty $basePathProperty;
    private mixed $originalBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalServer = $_SERVER;
        $this->basePathProperty = new ReflectionProperty(UrlHelper::class, 'basePath');
        $this->originalBasePath = $this->basePathProperty->getValue();
        $this->basePathProperty->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $this->basePathProperty->setValue(null, $this->originalBasePath);

        parent::tearDown();
    }

    /** @return iterable<string, array{string, string}> */
    public static function applicationPaths(): iterable
    {
        yield 'domain root' => [
            '/public/index.php',
            'https://apiosw.clubcheck.com.mx',
        ];

        yield 'subdirectory' => [
            '/clubcheck/public/index.php',
            'https://apiosw.clubcheck.com.mx/clubcheck',
        ];
    }

    #[DataProvider('applicationPaths')]
    public function test_base_url_builds_download_links_without_duplicate_slashes(
        string $scriptName,
        string $expectedBaseUrl,
    ): void {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'apiosw.clubcheck.com.mx';
        $_SERVER['SCRIPT_NAME'] = $scriptName;

        $baseUrl = Request::absoluteBaseUrl();

        self::assertSame($expectedBaseUrl, $baseUrl);

        foreach (['download', 'download-setup', 'download-zip', 'check-update'] as $endpoint) {
            self::assertSame(
                $expectedBaseUrl . '/api/' . $endpoint,
                $baseUrl . '/api/' . $endpoint,
            );
        }
    }
}
