<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerSessionWorkflowTest extends CustomerHttpTestCase
{
    public function test_starts_reuses_updates_lists_and_ends_a_session(): void
    {
        $payload = [
            'customerId' => $this->customerId,
            'deviceId' => 'characterization-device',
            'appVersion' => '9.9.9-test',
            'metadata' => ['source' => 'characterization'],
        ];
        $started = $this->requestAndRecord(
            'customers.sessions.lifecycle.start',
            'POST',
            '/api/customers/sessions/start',
            ['json' => $payload],
            [
                'status' => 201,
                'jsonSubset' => [
                    'customerId' => $this->customerId,
                    'status' => 'active',
                ],
            ],
            false,
        );

        self::assertSame(201, $started->getStatusCode());
        $sessionId = $this->responseJson($started)['sessionId'];
        self::assertNotSame('', $sessionId);

        $reused = $this->requestAndRecord(
            'customers.sessions.lifecycle.reuse',
            'POST',
            '/api/customers/sessions/start',
            ['json' => $payload],
            [
                'status' => 200,
                'jsonSubset' => [
                    'sessionId' => $sessionId,
                    'reused' => true,
                ],
            ],
            false,
        );
        self::assertTrue($this->responseJson($reused)['reused']);

        $heartbeat = $this->requestAndRecord(
            'customers.sessions.lifecycle.heartbeat',
            'POST',
            '/api/customers/sessions/heartbeat',
            [
                'json' => [
                    'sessionId' => $sessionId,
                    'metadata' => ['state' => 'running'],
                ],
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'sessionId' => $sessionId,
                    'status' => 'active',
                ],
            ],
            false,
        );
        self::assertSame(200, $heartbeat->getStatusCode());

        $active = $this->requestAndRecord(
            'customers.sessions.lifecycle.active',
            'GET',
            '/api/customers/sessions/active?customerId=' . rawurlencode($this->customerId),
            [],
            ['status' => 200, 'jsonSubset' => ['count' => 1]],
            false,
        );
        self::assertSame(1, $this->responseJson($active)['count']);

        $ended = $this->requestAndRecord(
            'customers.sessions.lifecycle.end',
            'POST',
            '/api/customers/sessions/end',
            [
                'json' => [
                    'sessionId' => $sessionId,
                    'reason' => 'characterization_complete',
                ],
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'sessionId' => $sessionId,
                    'status' => 'inactive',
                ],
            ],
            false,
        );
        self::assertSame(200, $ended->getStatusCode());

        $heartbeatAfterEnd = $this->requestAndRecord(
            'customers.sessions.lifecycle.heartbeat-after-end',
            'POST',
            '/api/customers/sessions/heartbeat',
            ['json' => ['sessionId' => $sessionId]],
            [
                'status' => 200,
                'jsonSubset' => [
                    'sessionId' => $sessionId,
                    'status' => 'active',
                ],
            ],
            false,
        );

        // Caracterización: actualmente heartbeat vuelve a activar una sesión
        // finalizada. La migración deberá conservarlo hasta que se decida
        // corregir este comportamiento en un cambio separado.
        self::assertSame(200, $heartbeatAfterEnd->getStatusCode());
    }

    public function test_rejects_invalid_and_oversized_metadata(): void
    {
        $invalid = $this->requestAndRecord(
            'customers.sessions.metadata.invalid',
            'POST',
            '/api/customers/sessions/start',
            [
                'json' => [
                    'customerId' => $this->customerId,
                    'metadata' => 'not-an-object',
                ],
            ],
            [
                'status' => 422,
                'jsonSubset' => [
                    'error' => 'Formato de metadatos inválido',
                    'message' => 'Metadata debe ser un objeto',
                ],
            ],
            false,
        );
        self::assertSame(422, $invalid->getStatusCode());

        $oversized = $this->requestAndRecord(
            'customers.sessions.metadata.oversized',
            'POST',
            '/api/customers/sessions/start',
            [
                'json' => [
                    'customerId' => $this->customerId,
                    'metadata' => ['value' => str_repeat('x', 2_100)],
                ],
            ],
            ['status' => 413, 'jsonSubset' => ['error' => 'Metadatos demasiado grandes']],
            false,
        );
        self::assertSame(413, $oversized->getStatusCode());
    }
}
