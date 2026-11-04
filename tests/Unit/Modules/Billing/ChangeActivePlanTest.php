<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Billing;

use App\Modules\Billing\Contracts\IBillingCustomerRepository;
use App\Modules\Billing\Contracts\IBillingGateway;
use App\Modules\Billing\Contracts\IBillingLicenseService;
use App\Modules\Billing\Services\BillingOperations;
use PHPUnit\Framework\TestCase;

final class ChangeActivePlanTest extends TestCase
{
    public function testItChangesTheAuthenticatedCustomersActiveSubscription(): void
    {
        $gateway = new ActivePlanChangeBillingGatewayFake();
        $gateway->responses['getActiveSubscription'] = [
            'success' => true,
            'has_subscription' => true,
            'subscription' => [
                'id' => 'sub_active_123',
                'status' => 'active',
            ],
        ];
        $gateway->responses['changePlan'] = [
            'success' => true,
            'subscription_id' => 'sub_active_123',
            'status' => 'active',
        ];
        $operations = $this->operations($gateway, 'cus_customer_1');

        $result = $operations->changeActivePlan(
            'customer-1',
            [
                'price_id' => 'price_new',
                'coupon_code' => 'SAVE10',
            ],
        );

        self::assertSame(200, $result->statusCode);
        self::assertSame('sub_active_123', $result->data['subscription_id']);
        self::assertSame(
            [
                [
                    'operation' => 'getActiveSubscription',
                    'arguments' => ['cus_customer_1'],
                ],
                [
                    'operation' => 'changePlan',
                    'arguments' => ['sub_active_123', 'price_new', 'SAVE10'],
                ],
                [
                    'operation' => 'getPlanRulesByLookupKey',
                    'arguments' => ['price_new'],
                ],
            ],
            $gateway->calls,
        );
    }

    public function testItReturnsNotFoundWithoutAnActiveSubscription(): void
    {
        $gateway = new ActivePlanChangeBillingGatewayFake();
        $gateway->responses['getActiveSubscription'] = [
            'success' => true,
            'has_subscription' => false,
            'subscription' => null,
        ];
        $operations = $this->operations($gateway, 'cus_customer_1');

        $result = $operations->changeActivePlan(
            'customer-1',
            ['price_id' => 'price_new'],
        );

        self::assertSame(404, $result->statusCode);
        self::assertSame(
            [
                'success' => false,
                'error' => 'No hay suscripción activa',
            ],
            $result->data,
        );
        self::assertCount(1, $gateway->calls);
    }

    public function testItValidatesThePriceBeforeConsultingStripe(): void
    {
        $gateway = new ActivePlanChangeBillingGatewayFake();
        $operations = $this->operations($gateway, 'cus_customer_1');

        $result = $operations->changeActivePlan('customer-1', []);

        self::assertSame(400, $result->statusCode);
        self::assertSame(
            [
                'success' => false,
                'error' => 'Se requiere price_id o plan_lookup_key válido',
            ],
            $result->data,
        );
        self::assertSame([], $gateway->calls);
    }

    private function operations(
        ActivePlanChangeBillingGatewayFake $gateway,
        ?string $billingId,
    ): BillingOperations
    {
        return new BillingOperations(
            $gateway,
            new ActivePlanChangeCustomerRepositoryFake($billingId),
            new ActivePlanChangeLicenseServiceFake(),
            [],
        );
    }
}

final class ActivePlanChangeBillingGatewayFake implements IBillingGateway
{
    public array $responses = [];
    public array $calls = [];

    public function execute(string $operation, array $arguments = []): mixed
    {
        $this->calls[] = [
            'operation' => $operation,
            'arguments' => $arguments,
        ];

        return $this->responses[$operation] ?? null;
    }
}

final readonly class ActivePlanChangeCustomerRepositoryFake implements IBillingCustomerRepository
{
    public function __construct(private ?string $billingId)
    {
    }

    public function customer(string $customerId): ?array
    {
        return [
            'customerId' => $customerId,
            'billingId' => $this->billingId,
        ];
    }

    public function findByBillingId(string $billingId): ?array
    {
        return null;
    }

    public function findByEmail(string $email): ?array
    {
        return null;
    }

    public function billingId(string $customerId, ?string $default = null): ?string
    {
        return $this->billingId ?? $default;
    }

    public function accessCodeByBillingId(string $billingId): ?string
    {
        return null;
    }
}

final class ActivePlanChangeLicenseServiceFake implements IBillingLicenseService
{
    public function available(): bool
    {
        return false;
    }

    public function verify(string $token): array
    {
        return [];
    }

    public function attach(
        array $result,
        array $customer,
        string $planKey,
        string $planName,
        bool $permanent,
        ?int $expiresAt,
    ): array
    {
        return $result;
    }
}
