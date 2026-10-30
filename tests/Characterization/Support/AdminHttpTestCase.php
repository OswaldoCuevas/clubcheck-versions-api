<?php

declare(strict_types=1);

namespace Tests\Characterization\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

abstract class AdminHttpTestCase extends TestCase
{
    protected TestEnvironment $environment;
    protected TestDatabase $database;
    protected Client $anonymousClient;
    private ?Client $adminClient = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->environment = TestEnvironment::instance();
        $this->database = $this->environment->database();
        if ($this->shouldResetFixtures()) {
            $this->database->resetFixtures();
        }
        $this->anonymousClient = $this->newClient();
    }

    protected function shouldResetFixtures(): bool
    {
        return true;
    }

    protected function adminClient(): Client
    {
        if ($this->adminClient instanceof Client) {
            return $this->adminClient;
        }

        return $this->adminClient = $this->loginClient('admin', 'admin-test-password');
    }

    protected function loginClient(string $username, string $password): Client
    {
        $client = $this->newClient(new CookieJar());
        $loginPage = $client->request('GET', '/login');
        self::assertSame(200, $loginPage->getStatusCode(), 'No se pudo abrir la pantalla de login de pruebas.');

        $html = (string) $loginPage->getBody();
        if (!preg_match('/name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']/', $html, $matches)) {
            throw new RuntimeException('No se encontró el CSRF del login.');
        }

        $login = $client->request('POST', '/login', [
            'form_params' => [
                'csrf_token' => html_entity_decode($matches[1], ENT_QUOTES),
                'username' => $username,
                'password' => $password,
            ],
        ]);
        self::assertSame(302, $login->getStatusCode(), 'El usuario administrador de prueba no pudo autenticarse.');

        return $client;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $expected
     */
    protected function requestAndRecord(
        string $caseId,
        string $method,
        string $path,
        array $options,
        array $expected,
        bool $authenticated = true
    ): ResponseInterface {
        $client = $authenticated ? $this->adminClient() : $this->anonymousClient;
        $response = $client->request($method, $path, $options);
        $this->environment->recorder()->record($caseId, [
            'method' => strtoupper($method),
            'path' => $path,
            'authenticated' => $authenticated,
            'options' => $options,
        ], $response, $expected);

        return $response;
    }

    /** @return array<string, mixed> */
    protected function responseJson(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $case */
    protected function assertInventoryCase(array $case): void
    {
        $request = $case['request'];
        $expected = $case['expected'];
        $response = $this->requestAndRecord(
            $case['id'],
            $request['method'],
            $request['path'],
            $request['options'] ?? [],
            $expected,
            $case['authenticated'] ?? true
        );

        self::assertSame((int) $expected['status'], $response->getStatusCode(), $case['name']);

        foreach ($expected['headerContains'] ?? [] as $header => $needle) {
            self::assertStringContainsString((string) $needle, $response->getHeaderLine((string) $header));
        }

        $body = (string) $response->getBody();
        foreach ($expected['bodyContains'] ?? [] as $needle) {
            self::assertStringContainsString((string) $needle, $body);
        }

        if (isset($expected['jsonSubset'])) {
            $actual = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $this->assertArraySubsetRecursive($expected['jsonSubset'], $actual);
        }
    }

    /** @return array<string, mixed> */
    protected static function inventory(): array
    {
        $path = dirname(__DIR__) . '/contracts/admin-controller.json';
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('No se pudo leer el inventario ' . $path);
        }
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function newClient(?CookieJar $cookies = null): Client
    {
        return new Client([
            'base_uri' => $this->environment->baseUri(),
            'http_errors' => false,
            'allow_redirects' => false,
            'cookies' => $cookies ?? new CookieJar(),
            'connect_timeout' => 2.0,
            'timeout' => 10.0,
            'headers' => ['User-Agent' => 'ClubCheck-Characterization/1.0'],
        ]);
    }

    private function assertArraySubsetRecursive(mixed $expected, mixed $actual, string $path = '$'): void
    {
        if (!is_array($expected)) {
            self::assertSame($expected, $actual, 'Diferencia en ' . $path);
            return;
        }

        self::assertIsArray($actual, 'Se esperaba un arreglo en ' . $path);
        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $actual, 'Falta la clave ' . $path . '.' . $key);
            $this->assertArraySubsetRecursive($value, $actual[$key], $path . '.' . $key);
        }
    }
}
