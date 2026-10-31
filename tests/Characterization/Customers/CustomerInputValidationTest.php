<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerInputValidationTest extends CustomerHttpTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>, 2: string}
     */
    public static function invalidPublicPayloads(): iterable
    {
        yield 'register invalid email' => [
            '/api/customers/register',
            [
                'name' => 'Cliente',
                'token' => 'machine-token',
                'email' => 'not-an-email',
            ],
            'Formato de correo inválido',
        ];
        yield 'login invalid email' => [
            '/api/customers/login',
            [
                'email' => 'not-an-email',
                'accessKey' => 'ACCESS-KEY',
                'deviceName' => 'PC',
                'token' => 'machine-token',
            ],
            'Formato de correo inválido',
        ];
        yield 'validate invalid email' => [
            '/api/customers/validate',
            [
                'name' => 'Cliente',
                'token' => 'machine-token',
                'email' => 'not-an-email',
            ],
            'Formato de correo inválido',
        ];
        yield 'validate plan code too long' => [
            '/api/customers/validate',
            [
                'name' => 'Cliente',
                'token' => 'machine-token',
                'planCode' => 'x' . str_repeat('y', 50),
            ],
            'El PlanCode debe tener máximo 50 caracteres',
        ];
    }

    #[DataProvider('invalidPublicPayloads')]
    public function test_rejects_invalid_public_payloads(
        string $path,
        array $payload,
        string $error,
    ): void {
        $response = $this->requestAndRecord(
            'customers.validation.' . substr(hash('sha256', $path . $error), 0, 12),
            'POST',
            $path,
            ['json' => $payload],
            ['status' => 422, 'jsonSubset' => ['error' => $error]],
            false,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame($error, $this->responseJson($response)['error']);
    }

    public function test_rejects_malformed_json(): void
    {
        $response = $this->requestAndRecord(
            'customers.validation.malformed-json',
            'POST',
            '/api/customers/sessions/start',
            [
                'body' => '{invalid-json',
                'headers' => ['Content-Type' => 'application/json'],
            ],
            ['status' => 400, 'jsonSubset' => ['error' => 'Carga JSON inválida']],
            false,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function test_desktop_sync_rejects_malformed_json(): void
    {
        foreach (['pull', 'push'] as $operation) {
            $response = $this->requestAndRecord(
                'customers.desktop.' . $operation . '.malformed-json',
                'POST',
                '/api/customers/desktop/' . $operation,
                [
                    'body' => '{invalid-json',
                    'headers' => ['Content-Type' => 'application/json'],
                ],
                [
                    'status' => 400,
                    'jsonSubset' => ['error' => 'Carga JSON inválida'],
                ],
            );

            self::assertSame(400, $response->getStatusCode());
        }
    }

    public function test_desktop_sync_rejects_wrong_http_methods(): void
    {
        foreach (['pull', 'push'] as $operation) {
            $response = $this->requestAndRecord(
                'customers.desktop.' . $operation . '.get',
                'GET',
                '/api/customers/desktop/' . $operation,
                [],
                [
                    'status' => 405,
                    'jsonSubset' => ['error' => 'Método no permitido'],
                ],
            );

            self::assertSame(405, $response->getStatusCode());
        }
    }

    public function test_rejects_wrong_http_methods(): void
    {
        $postOnly = $this->requestAndRecord(
            'customers.methods.start.get',
            'GET',
            '/api/customers/sessions/start',
            [],
            ['status' => 405, 'jsonSubset' => ['error' => 'Método no permitido']],
            false,
        );
        self::assertSame(405, $postOnly->getStatusCode());

        $getOnly = $this->requestAndRecord(
            'customers.methods.token.post',
            'POST',
            '/api/customers/token',
            ['json' => ['customerId' => $this->customerId]],
            ['status' => 405, 'jsonSubset' => ['error' => 'Método no permitido']],
            false,
        );
        self::assertSame(405, $getOnly->getStatusCode());

        $explicitRoute = $this->requestAndRecord(
            'customers.methods.validate.get',
            'GET',
            '/api/customers/validate',
            [],
            ['status' => 401, 'jsonSubset' => ['error_code' => 'TOKEN_MISSING']],
            false,
        );

        // Caracterización: como la ruta validate solo acepta POST, el GET
        // termina coincidiendo con /api/customers/:customerId y ejecuta su
        // middleware customer_jwt.
        self::assertSame(401, $explicitRoute->getStatusCode());
    }
}
