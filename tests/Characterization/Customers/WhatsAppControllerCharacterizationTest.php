<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Characterization\Support\CustomerHttpTestCase;

final class WhatsAppControllerCharacterizationTest extends CustomerHttpTestCase
{
    public function testStatusIsLocalAndPreservesItsShape(): void
    {
        $response = $this->requestAndRecord(
            'whatsapp-public-status',
            'GET',
            '/api/customers/whatsapp/status',
            [],
            ['status' => 200],
            false,
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertArrayHasKey('configured', $body);
        self::assertIsBool($body['configured']);
        self::assertIsInt($body['timestamp']);
    }

    public function testMonthlyCountUsesTheAuthenticatedCustomer(): void
    {
        $response = $this->requestAndRecord(
            'whatsapp-monthly-count',
            'GET',
            '/api/customers/whatsapp/monthly-count/another-customer',
            [],
            ['status' => 200],
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($this->customerId, $body['customerApiId']);
        self::assertSame(0, $body['count']);
        self::assertSame(date('Y-m'), $body['month']);
    }

    public function testMessageListStartsEmpty(): void
    {
        $response = $this->requestAndRecord(
            'whatsapp-message-list',
            'GET',
            '/api/customers/whatsapp/messages/another-customer',
            [],
            ['status' => 200],
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($this->customerId, $body['customerApiId']);
        self::assertSame([], $body['messages']);
        self::assertSame([], $body['filters']);
    }

    #[DataProvider('sendEndpoints')]
    public function testSendEndpointsValidateBeforeCallingMeta(string $path): void
    {
        $response = $this->requestAndRecord(
            'whatsapp-send-validation-' . basename($path),
            'POST',
            $path,
            ['json' => []],
            ['status' => 422],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            ['subscriptionId', 'phone'],
            $this->responseJson($response)['missing'],
        );
    }

    public static function sendEndpoints(): array
    {
        return [
            ['/api/customers/whatsapp/send/subscription'],
            ['/api/customers/whatsapp/send/warning'],
            ['/api/customers/whatsapp/send/finalized'],
            ['/api/customers/whatsapp/send/last-day'],
        ];
    }

    public function testEmptyBulkDoesNotCallMeta(): void
    {
        $response = $this->requestAndRecord(
            'whatsapp-empty-bulk',
            'POST',
            '/api/customers/whatsapp/send/bulk',
            ['json' => ['items' => []]],
            ['status' => 200],
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [
                'success' => [],
                'failed' => [],
                'total' => 0,
                'successCount' => 0,
                'failedCount' => 0,
            ],
            $this->responseJson($response),
        );
    }
}
