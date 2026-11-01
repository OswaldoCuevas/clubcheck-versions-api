<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\DesktopReporting;

use App\Exceptions\ApiException;
use App\Modules\DesktopReporting\Contracts\IDesktopReportingRepository;
use App\Modules\DesktopReporting\Domain\DesktopReportAccessPolicy;
use App\Modules\DesktopReporting\Domain\DesktopReportQueryPolicy;
use App\Modules\DesktopReporting\Features\ReportQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DesktopReportFeaturesTest extends TestCase
{
    /**
     * @param class-string<ReportQuery> $queryClass
     * @param class-string $handlerClass
     */
    #[DataProvider('featureProvider')]
    public function test_each_feature_validates_access_and_delegates_to_its_repository_operation(
        string $queryClass,
        string $handlerClass,
        string $repositoryMethod,
    ): void {
        $repository = $this->createMock(IDesktopReportingRepository::class);
        $repository
            ->expects(self::once())
            ->method($repositoryMethod)
            ->with(
                'customer-1',
                ['range' => 'week'],
            )
            ->willReturn(['feature' => $repositoryMethod]);
        $handler = new $handlerClass(
            $repository,
            new DesktopReportAccessPolicy(),
            new DesktopReportQueryPolicy(),
        );

        $result = $handler->handle(new $queryClass(
            'customer-1',
            ['range' => 'week'],
        ));

        self::assertSame(
            ['feature' => $repositoryMethod],
            $result->data,
        );
    }

    public function test_custom_range_requires_both_valid_dates(): void
    {
        $policy = new DesktopReportQueryPolicy();

        try {
            $policy->validate([
                'range' => 'custom',
                'from' => 'not-a-date',
            ]);
            self::fail('Expected invalid custom dates to fail.');
        } catch (ApiException $exception) {
            self::assertSame(
                ['error' => 'from y to son obligatorios para range=custom'],
                $exception->toArray(),
            );
        }
    }

    public function test_custom_range_rejects_reversed_dates(): void
    {
        $policy = new DesktopReportQueryPolicy();

        try {
            $policy->validate([
                'range' => 'custom',
                'from' => '2026-11-01',
                'to' => '2026-10-01',
            ]);
            self::fail('Expected reversed custom dates to fail.');
        } catch (ApiException $exception) {
            self::assertSame(
                ['error' => 'from no puede ser mayor que to'],
                $exception->toArray(),
            );
        }
    }

    /** @return iterable<string, array{class-string, class-string, string}> */
    public static function featureProvider(): iterable
    {
        $features = [
            'dashboard' => 'GetDashboard',
            'users' => 'ListUsers',
            'memberships' => 'ListMemberships',
            'membershipSales' => 'ListMembershipSales',
            'products' => 'ListProducts',
            'attendances' => 'ListAttendances',
            'sales' => 'ListSales',
            'admins' => 'ListAdministrators',
            'chartsOverview' => 'GetChartsOverview',
            'chartsUsers' => 'GetUserCharts',
            'chartsMemberships' => 'GetMembershipCharts',
            'chartsProducts' => 'GetProductCharts',
            'chartsAttendances' => 'GetAttendanceCharts',
            'chartsSales' => 'GetSalesCharts',
        ];

        foreach ($features as $repositoryMethod => $feature) {
            $namespace = 'App\\Modules\\DesktopReporting\\Features\\' . $feature;

            yield $repositoryMethod => [
                $namespace . '\\Query',
                $namespace . '\\Handler',
                $repositoryMethod,
            ];
        }
    }
}
