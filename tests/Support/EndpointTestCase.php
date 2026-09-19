<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class EndpointTestCase extends TestCase
{
    protected function requireEndpointTests(): void
    {
        if ($this->env('RUN_ENDPOINT_TESTS') !== '1') {
            $this->markTestSkipped('Activa RUN_ENDPOINT_TESTS=1 en .env.testing.');
        }
    }

    protected function requireCustomerJwt(): string
    {
        $jwt = $this->env('TEST_CUSTOMER_JWT');
        if ($jwt === '') {
            $this->markTestSkipped('Falta TEST_CUSTOMER_JWT.');
        }

        return $jwt;
    }

    protected function env(string $name, string $default = ''): string
    {
        $value = $_ENV[$name] ?? getenv($name);
        return $value === false || $value === null ? $default : trim((string) $value);
    }

    /** @return array{status:int,json:array<string,mixed>|null,body:string} */
    protected function request(string $method, string $path, ?array $json = null, ?string $jwt = null): array
    {
        $baseUrl = rtrim($this->env('TEST_BASE_URL'), '/');
        if ($baseUrl === '') {
            $this->markTestSkipped('Falta TEST_BASE_URL.');
        }

        $handle = curl_init($baseUrl . '/' . ltrim($path, '/'));
        $headers = ['Accept: application/json'];
        if ($jwt !== null && $jwt !== '') {
            $headers[] = 'Authorization: Bearer ' . $jwt;
        }
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($json, JSON_THROW_ON_ERROR));
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $body = curl_exec($handle);
        if ($body === false) {
            $error = curl_error($handle);
            curl_close($handle);
            self::fail('No se pudo consultar el endpoint: ' . $error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        $decoded = json_decode((string) $body, true);

        return [
            'status' => $status,
            'json' => is_array($decoded) ? $decoded : null,
            'body' => (string) $body,
        ];
    }

    protected function assertJsonResponse(array $response): array
    {
        self::assertNotSame(0, $response['status'], 'El servidor no devolvio un status HTTP.');
        self::assertIsArray($response['json'], 'Respuesta no JSON: ' . $response['body']);
        return $response['json'];
    }
}
