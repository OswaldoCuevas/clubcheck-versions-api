<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Characterization\Support\CustomerHttpTestCase;

final class StripeControllerCharacterizationTest extends CustomerHttpTestCase
{
    public function testPublicConfigPreservesItsShape(): void
    {
        $response = $this->requestAndRecord(
            'stripe-public-config',
            'GET',
            '/api/customers/stripe/config',
            [],
            ['status' => 200],
            false,
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($body['success']);
        self::assertArrayHasKey('public_key', $body);
        self::assertArrayHasKey('plans', $body);
    }

    public function testPlansPreserveTheirShape(): void
    {
        $response = $this->requestAndRecord(
            'stripe-plans',
            'GET',
            '/api/customers/stripe/plans',
            [],
            ['status' => 200],
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($body['success']);
        self::assertIsArray($body['plans']);
    }

    #[DataProvider('validationCases')]
    public function testLocalValidationContracts(
        string $caseId,
        string $method,
        string $path,
        array $json,
        int $status,
        array $expected,
        bool $authenticated = true,
    ): void {
        $response = $this->requestAndRecord(
            $caseId,
            $method,
            $path,
            ['json' => $json],
            ['status' => $status],
            $authenticated,
        );

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($expected, $this->responseJson($response));
    }

    public static function validationCases(): array
    {
        return [
            'validate license' => [
                'stripe-license-required',
                'POST',
                '/api/licenses/validate',
                [],
                422,
                ['error' => 'licenseToken es obligatorio'],
                false,
            ],
            'add card' => [
                'stripe-card-token-required',
                'POST',
                '/api/customers/stripe/customers/customer-test-1/cards',
                [],
                400,
                ['success' => false, 'error' => 'Campo requerido: token_id'],
            ],
            'create subscription' => [
                'stripe-subscription-price-required',
                'POST',
                '/api/customers/stripe/customers/customer-test-1/subscriptions',
                [],
                400,
                ['success' => false, 'error' => 'Se requiere price_id o plan_lookup_key válido'],
            ],
            'update subscription' => [
                'stripe-subscription-cancel-required',
                'PUT',
                '/api/customers/stripe/subscriptions/sub_test',
                [],
                400,
                ['success' => false, 'error' => 'Se requiere cancel_at_period_end'],
            ],
            'change plan' => [
                'stripe-change-plan-price-required',
                'PUT',
                '/api/customers/stripe/subscriptions/sub_test/plan',
                [],
                400,
                ['success' => false, 'error' => 'Se requiere price_id o plan_lookup_key válido'],
            ],
            'refresh license' => [
                'stripe-refresh-date-required',
                'POST',
                '/api/customers/stripe/license/refresh',
                [],
                400,
                ['success' => false, 'error' => 'Se requiere subscription_date'],
            ],
            'validate coupon' => [
                'stripe-coupon-required',
                'POST',
                '/api/customers/stripe/coupons/validate',
                [],
                400,
                ['success' => false, 'error' => 'Campo requerido: coupon_code'],
                false,
            ],
            'preview plan change' => [
                'stripe-preview-change-price-required',
                'POST',
                '/api/customers/stripe/subscriptions/sub_test/preview',
                [],
                400,
                ['success' => false, 'error' => 'Se requiere price_id o plan_lookup_key válido'],
            ],
            'preview subscription' => [
                'stripe-preview-new-price-required',
                'POST',
                '/api/customers/stripe/customers/customer-test-1/subscriptions/preview',
                [],
                400,
                ['success' => false, 'error' => 'Se requiere price_id o plan_lookup_key válido'],
            ],
        ];
    }
}
