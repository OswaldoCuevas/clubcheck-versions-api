<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Billing;

use App\Modules\Billing\Services\PlanChangeProrationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlanChangeProrationPolicyTest extends TestCase
{
    /**
     * @param array{proration_behavior: string, billing_cycle_anchor: string} $expected
     */
    #[DataProvider('periodChanges')]
    public function testItAppliesTheExpectedProrationRule(
        string $currentInterval,
        int $currentIntervalCount,
        string $newInterval,
        int $newIntervalCount,
        array $expected,
    ): void
    {
        $policy = new PlanChangeProrationPolicy();

        $settings = $policy->settings(
            $currentInterval,
            $currentIntervalCount,
            $newInterval,
            $newIntervalCount,
        );

        self::assertSame($expected, $settings);
    }

    /**
     * @return iterable<string, array{
     *     string,
     *     int,
     *     string,
     *     int,
     *     array{proration_behavior: string, billing_cycle_anchor: string}
     * }>
     */
    public static function periodChanges(): iterable
    {
        yield 'monthly to monthly is prorated' => [
            'month',
            1,
            'month',
            1,
            [
                'proration_behavior' => 'create_prorations',
                'billing_cycle_anchor' => 'unchanged',
            ],
        ];

        yield 'monthly to yearly is not prorated' => [
            'month',
            1,
            'year',
            1,
            [
                'proration_behavior' => 'none',
                'billing_cycle_anchor' => 'now',
            ],
        ];

        yield 'yearly to monthly is not prorated' => [
            'year',
            1,
            'month',
            1,
            [
                'proration_behavior' => 'none',
                'billing_cycle_anchor' => 'now',
            ],
        ];

        yield 'yearly to yearly is not prorated' => [
            'year',
            1,
            'year',
            1,
            [
                'proration_behavior' => 'none',
                'billing_cycle_anchor' => 'unchanged',
            ],
        ];

        yield 'one month to three months is not prorated' => [
            'month',
            1,
            'month',
            3,
            [
                'proration_behavior' => 'none',
                'billing_cycle_anchor' => 'now',
            ],
        ];
    }
}
