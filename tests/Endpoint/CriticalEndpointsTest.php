<?php

declare(strict_types=1);

namespace Tests\Endpoint;

use Tests\Support\EndpointTestCase;

require_once dirname(__DIR__) . '/Support/EndpointTestCase.php';

final class CriticalEndpointsTest extends EndpointTestCase
{
    public function testPublicStripeConfigurationUsesTestPublishableKey(): void
    {
        $this->requireEndpointTests();
        $response = $this->request('GET', '/api/customers/stripe/config');
        $json = $this->assertJsonResponse($response);

        self::assertSame(200, $response['status']);
        self::assertTrue($json['success'] ?? false);
        self::assertStringStartsWith('pk_test_', (string) ($json['public_key'] ?? ''));
        self::assertIsArray($json['plans'] ?? null);
    }

    public function testWhatsAppStatusIsReadOnlyAndReturnsContract(): void
    {
        $this->requireEndpointTests();
        $response = $this->request('GET', '/api/customers/whatsapp/status');
        $json = $this->assertJsonResponse($response);

        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('configured', $json);
        self::assertIsBool($json['configured']);
        self::assertIsInt($json['timestamp']);
    }

    public function testSensitiveEndpointsRejectRequestsWithoutCustomerJwt(): void
    {
        $this->requireEndpointTests();

        $requests = [
            ['POST', '/api/customers/desktop/pull', ['customerApiId' => 'not-authorized']],
            ['POST', '/api/customers/desktop/push', ['customerApiId' => 'not-authorized', 'bulks' => []]],
            ['GET', '/api/customers/stripe/prices', null],
            ['POST', '/api/customers/whatsapp/send/subscription', [
                'customerApiId' => 'not-authorized',
                'subscriptionId' => 'must-not-send',
                'phone' => '526141234567',
            ]],
        ];

        foreach ($requests as [$method, $path, $body]) {
            $response = $this->request($method, $path, $body);
            $this->assertJsonResponse($response);
            self::assertContains($response['status'], [401, 403], $path . ' debe requerir JWT.');
        }
    }

    public function testWhatsAppMissingFieldsAreRejectedBeforeSending(): void
    {
        $this->requireEndpointTests();
        $jwt = $this->requireCustomerJwt();

        $response = $this->request('POST', '/api/customers/whatsapp/send/subscription', [], $jwt);
        $json = $this->assertJsonResponse($response);

        self::assertSame(422, $response['status']);
        self::assertArrayHasKey('missing', $json);
        self::assertContains('subscriptionId', $json['missing']);
        self::assertContains('phone', $json['missing']);
    }
}
