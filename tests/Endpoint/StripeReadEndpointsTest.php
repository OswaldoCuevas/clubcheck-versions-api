<?php

declare(strict_types=1);

namespace Tests\Endpoint;

use Tests\Support\EndpointTestCase;

require_once dirname(__DIR__) . '/Support/EndpointTestCase.php';

final class StripeReadEndpointsTest extends EndpointTestCase
{
    public function testStripeReadEndpointsReturnTheirDocumentedInformation(): void
    {
        $this->requireEndpointTests();
        $jwt = $this->requireCustomerJwt();
        $billingId = $this->env('TEST_STRIPE_BILLING_ID');
        if ($billingId === '') {
            $this->markTestSkipped('Falta TEST_STRIPE_BILLING_ID.');
        }

        $plansResponse = $this->request('GET', '/api/customers/stripe/plans', null, $jwt);
        $plans = $this->assertJsonResponse($plansResponse);
        self::assertSame(200, $plansResponse['status']);
        self::assertTrue($plans['success'] ?? false);
        self::assertIsArray($plans['plans'] ?? null);
        foreach ($plans['plans'] as $plan) {
            self::assertArrayHasKey('name', $plan);
            self::assertArrayHasKey('lookup_key', $plan);
            self::assertArrayHasKey('rules', $plan);
            self::assertArrayHasKey('type', $plan);
        }

        $pricesResponse = $this->request('GET', '/api/customers/stripe/prices', null, $jwt);
        $prices = $this->assertJsonResponse($pricesResponse);
        self::assertSame(200, $pricesResponse['status']);
        self::assertTrue($prices['success'] ?? false, $prices['error'] ?? 'Fallo prices');
        self::assertIsArray($prices['prices'] ?? null);

        $customerResponse = $this->request(
            'GET', '/api/customers/stripe/customers/' . rawurlencode($billingId), null, $jwt
        );
        $customer = $this->assertJsonResponse($customerResponse);
        self::assertSame(200, $customerResponse['status']);
        self::assertTrue($customer['success'] ?? false, $customer['error'] ?? 'Fallo customer');
        self::assertArrayHasKey('customer', $customer);

        $cardsResponse = $this->request(
            'GET', '/api/customers/stripe/customers/' . rawurlencode($billingId) . '/cards', null, $jwt
        );
        $cards = $this->assertJsonResponse($cardsResponse);
        self::assertSame(200, $cardsResponse['status']);
        self::assertTrue($cards['success'] ?? false, $cards['error'] ?? 'Fallo cards');
        self::assertIsArray($cards['cards'] ?? null);

        $subscriptionResponse = $this->request(
            'GET', '/api/customers/stripe/customers/' . rawurlencode($billingId) . '/subscriptions/active', null, $jwt
        );
        $subscription = $this->assertJsonResponse($subscriptionResponse);
        self::assertContains($subscriptionResponse['status'], [200, 400]);
        self::assertArrayHasKey('success', $subscription);

        $planResponse = $this->request(
            'GET', '/api/customers/stripe/customers/' . rawurlencode($billingId) . '/plan', null, $jwt
        );
        $currentPlan = $this->assertJsonResponse($planResponse);
        self::assertSame(200, $planResponse['status']);
        self::assertTrue($currentPlan['success'] ?? false, $currentPlan['error'] ?? 'Fallo current plan');
        self::assertIsArray($currentPlan['plan'] ?? null);
        self::assertArrayHasKey('lookup_key', $currentPlan['plan']);
        self::assertArrayHasKey('rules', $currentPlan['plan']);
    }
}
