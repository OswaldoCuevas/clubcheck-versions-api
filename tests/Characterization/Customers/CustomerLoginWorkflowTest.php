<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerLoginWorkflowTest extends CustomerHttpTestCase
{
    private const ACCESS_KEY = 'characterization-access-key';

    public function test_login_rejects_invalid_credentials_and_records_the_attempt(): void
    {
        $response = $this->requestAndRecord(
            'customers.login.invalid-credentials',
            'POST',
            '/api/customers/login',
            [
                'json' => [
                    'email' => 'cliente1@example.test',
                    'accessKey' => 'wrong-access-key',
                    'deviceName' => 'LOGIN-PC',
                    'token' => 'new-machine-token',
                ],
            ],
            [
                'status' => 401,
                'jsonSubset' => ['error' => 'Credenciales inválidas'],
            ],
            false,
        );

        self::assertSame(401, $response->getStatusCode());

        $attempt = $this->database->fetchOne(
            'SELECT CustomerId, DeviceName, WasSuccessful
             FROM CustomerLoginAttempts
             WHERE Email = ?',
            ['cliente1@example.test'],
        );
        self::assertSame($this->customerId, $attempt['CustomerId']);
        self::assertSame('LOGIN-PC', $attempt['DeviceName']);
        self::assertSame(0, (int) $attempt['WasSuccessful']);
    }

    public function test_login_rejects_a_customer_that_is_not_waiting_for_a_token(): void
    {
        $this->storeValidAccessKey(false);

        $response = $this->requestAndRecord(
            'customers.login.not-waiting',
            'POST',
            '/api/customers/login',
            ['json' => $this->validLoginPayload()],
            [
                'status' => 409,
                'jsonSubset' => ['error' => 'El cliente no está esperando un nuevo token'],
            ],
            false,
        );

        self::assertSame(409, $response->getStatusCode());
    }

    public function test_login_blocks_an_email_after_five_recent_failures(): void
    {
        $values = [];
        $parameters = [];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $values[] = '(?, ?, ?, ?, 0, NOW())';
            array_push(
                $parameters,
                'cliente1@example.test',
                $this->customerId,
                '127.0.0.1',
                'RATE-LIMIT-PC',
            );
        }

        $this->database->connection()->execute_query(
            'INSERT INTO CustomerLoginAttempts (
                Email, CustomerId, IpAddress, DeviceName, WasSuccessful, CreatedAt
             ) VALUES ' . implode(', ', $values),
            $parameters,
        );

        $response = $this->requestAndRecord(
            'customers.login.rate-limited',
            'POST',
            '/api/customers/login',
            ['json' => $this->validLoginPayload()],
            [
                'status' => 429,
                'jsonSubset' => [
                    'error' => 'Demasiados intentos fallidos. Intenta nuevamente en una hora.',
                ],
            ],
            false,
        );

        self::assertSame(429, $response->getStatusCode());
    }

    private function storeValidAccessKey(bool $waiting): void
    {
        $secret = $this->database->serverEnvironment()['ACCESS_KEY_SECRET'];
        $accessKeyHash = hash_hmac('sha512', self::ACCESS_KEY, $secret);

        $this->database->connection()->execute_query(
            'UPDATE Customers
             SET AccessKeyHash = ?, WaitingForToken = ?, WaitingSince = NOW()
             WHERE Id = ?',
            [$accessKeyHash, $waiting ? 1 : 0, $this->customerId],
        );
    }

    /** @return array<string, string> */
    private function validLoginPayload(): array
    {
        return [
            'email' => 'cliente1@example.test',
            'accessKey' => self::ACCESS_KEY,
            'deviceName' => 'LOGIN-PC',
            'token' => 'new-machine-token',
        ];
    }
}
