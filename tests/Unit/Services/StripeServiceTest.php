<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\StripeService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class StripeServiceTest extends TestCase
{
    private StripeService $service;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 3) . '/app/Services/StripeService.php';
        $this->service = new StripeService('sk_test_unit_only');

        $plans = [
            'free' => [
                'name' => 'Free',
                'lookup_key' => 'free',
                'rules' => ['max_messages' => 5],
                'type' => 'monthly',
            ],
            'public_monthly' => [
                'name' => 'Publico',
                'lookup_key' => 'public_monthly',
                'rules' => ['max_messages' => 100],
                'type' => 'monthly',
            ],
            'private_monthly' => [
                'name' => 'Privado',
                'lookup_key' => 'private_monthly',
                'rules' => ['max_messages' => 500],
                'type' => 'monthly',
                'showBillingIds' => ['cus_allowed'],
            ],
        ];

        $property = (new ReflectionClass(StripeService::class))->getProperty('plansCache');
        $property->setValue($this->service, $plans);
    }

    public function testPlanRulesAreReturnedWithCompleteInformation(): void
    {
        $plan = $this->service->getPlanRulesByLookupKey('private_monthly');

        self::assertSame('Privado', $plan['name']);
        self::assertSame('private_monthly', $plan['lookup_key']);
        self::assertSame(['max_messages' => 500], $plan['rules']);
        self::assertSame('monthly', $plan['type']);
        self::assertSame(['cus_allowed'], $plan['showBillingIds']);
    }

    public function testPrivatePlansAreOnlyVisibleToAllowedBillingId(): void
    {
        self::assertArrayNotHasKey('private_monthly', $this->service->getVisibleConfiguredPlans('cus_other'));
        self::assertArrayHasKey('private_monthly', $this->service->getVisibleConfiguredPlans('cus_allowed'));
    }

    public function testKnownPlanDisplayNamesAndFallback(): void
    {
        self::assertSame('Plan Profesional', $this->service->resolvePlanDisplayName('professional_monthly'));
        self::assertSame('Plan actual', $this->service->resolvePlanDisplayName('custom_plan'));
    }
}
