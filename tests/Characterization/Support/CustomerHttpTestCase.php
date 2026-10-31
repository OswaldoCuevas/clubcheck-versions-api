<?php

declare(strict_types=1);

namespace Tests\Characterization\Support;

use App\Services\JwtService;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

abstract class CustomerHttpTestCase extends TestCase
{
    protected TestEnvironment $environment;
    protected TestDatabase $database;
    protected Client $anonymousClient;
    protected Client $customerClient;
    protected string $customerId = 'customer-test-1';
    protected string $machineToken = 'machine-token-test';
    protected string $customerJwt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environment = TestEnvironment::instance();
        $this->database = $this->environment->database();
        $this->database->resetFixtures();
        $this->database->insertCustomer();

        $jwtSecret = $this->database->serverEnvironment()['JWT_SECRET_KEY'];
        $this->customerJwt = (new JwtService($jwtSecret))->createToken(
            [
                'cid' => $this->customerId,
                'mkt' => $this->machineToken,
                'typ' => 'customer',
            ],
            0,
        );

        $this->database->connection()->execute_query(
            'UPDATE Customers
             SET TokenJwt = ?, TokenJwtCreatedAt = ?, TokenJwtExpiresAt = NULL
             WHERE Id = ?',
            [$this->customerJwt, date('Y-m-d H:i:s'), $this->customerId],
        );

        $this->anonymousClient = $this->newClient();
        $this->customerClient = $this->newClient([
            'Authorization' => 'Bearer ' . $this->customerJwt,
            'X-Device-Name' => 'CHARACTERIZATION-PC',
        ]);
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
        bool $authenticated = true,
    ): ResponseInterface {
        $client = $authenticated ? $this->customerClient : $this->anonymousClient;
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
            $case['authenticated'] ?? false,
        );

        self::assertSame((int) $expected['status'], $response->getStatusCode(), $case['name']);

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
        $path = dirname(__DIR__) . '/contracts/customers-controller.json';
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
            'timeout' => 15.0,
            'headers' => array_replace(
                ['User-Agent' => 'ClubCheck-Customer-Characterization/1.0'],
                $headers,
            ),
        ]);
    }

    private function assertArraySubsetRecursive(
        mixed $expected,
        mixed $actual,
        string $path = '$',
    ): void {
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
