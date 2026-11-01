<?php

declare(strict_types=1);

namespace Tests\Characterization\Support;

use App\Services\JwtService;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

abstract class DesktopHttpTestCase extends TestCase
{
    protected TestEnvironment $environment;
    protected TestDatabase $database;
    protected Client $anonymousClient;
    protected Client $desktopClient;
    protected string $customerId = 'desktop-customer-test';
    protected string $adminId = 'desktop-admin-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->environment = TestEnvironment::instance();
        $this->database = $this->environment->database();
        $this->database->resetFixtures();
        $this->database->insertCustomer([
            'Id' => $this->customerId,
            'Name' => 'Gimnasio Desktop',
            'CodeAccess' => 'DESKTOP-TEST',
            'Email' => 'desktop@example.test',
        ]);
        $this->database->connection()->execute_query(
            'INSERT INTO AdministratorsDesktop
                (Id, CustomerApiId, Username, Password, Email, Role, Removed)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $this->adminId,
                $this->customerId,
                'desktop-admin',
                password_hash('desktop-password', PASSWORD_BCRYPT),
                'desktop-admin@example.test',
                2,
                0,
            ],
        );

        $secret = $this->database->serverEnvironment()['JWT_SECRET_KEY'];
        $token = (new JwtService($secret))->createToken([
            'name' => 'Gimnasio Desktop',
            'customerId' => $this->customerId,
            'adminId' => $this->adminId,
            'role' => 2,
        ], 3600);

        $this->anonymousClient = $this->newClient();
        $this->desktopClient = $this->newClient([
            'Authorization' => 'Bearer ' . $token,
        ]);
    }

    /** @param array<string, mixed> $options @param array<string, mixed> $expected */
    protected function requestAndRecord(
        string $caseId,
        string $method,
        string $path,
        array $options,
        array $expected,
        bool $authenticated = true,
    ): ResponseInterface {
        $client = $authenticated ? $this->desktopClient : $this->anonymousClient;
        $response = $client->request($method, $path, $options);

        $this->environment->recorder()->record(
            $caseId,
            [
                'method' => strtoupper($method),
                'path' => $path,
                'authenticated' => $authenticated,
                'options' => $options,
            ],
            $response,
            $expected,
        );

        return $response;
    }

    /** @return array<string, mixed> */
    protected function responseJson(ResponseInterface $response): array
    {
        return json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<string, mixed> */
    protected static function inventory(): array
    {
        $path = dirname(__DIR__) . '/contracts/desktop-api-controller.json';
        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException('No se pudo leer el inventario ' . $path);
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, string> $headers */
    private function newClient(array $headers = []): Client
    {
        return new Client([
            'base_uri' => $this->environment->baseUri(),
            'http_errors' => false,
            'allow_redirects' => false,
            'connect_timeout' => 2.0,
            'timeout' => 20.0,
            'headers' => array_replace(
                ['User-Agent' => 'ClubCheck-Desktop-Characterization/1.0'],
                $headers,
            ),
        ]);
    }
}
