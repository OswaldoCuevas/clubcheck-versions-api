<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\DesktopReporting;

use App\Exceptions\ApiException;
use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\DesktopReporting\Domain\DesktopUserProfileQueryPolicy;
use App\Modules\DesktopReporting\Repositories\DesktopUserProfileRepository;
use PHPUnit\Framework\TestCase;

final class DesktopUserProfileDatabase implements IDatabaseConnection
{
    public array $oneRows = [];
    public array $allRows = [];
    public array $queries = [];

    public function fetchAll(string $sql, array $params = []): array
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return array_shift($this->allRows) ?? [];
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
        ];

        return array_shift($this->oneRows);
    }

    public function execute(string $sql, array $params = []): void
    {
    }

    public function insert(string $table, array $data): int
    {
        return 1;
    }

    public function update(
        string $table,
        array $data,
        string $where,
        array $whereParams = [],
    ): bool {
        return true;
    }

    public function delete(
        string $table,
        string $where,
        array $params = [],
        ?int $limit = 1,
    ): bool {
        return true;
    }

    public function begin(): void
    {
    }

    public function commitTransaction(): void
    {
    }

    public function rollbackTransaction(): void
    {
    }
}

final class DesktopUserProfileFeatureTest extends TestCase
{
    public function testRepositoryAlwaysScopesAUserToItsCustomer(): void
    {
        $database = new DesktopUserProfileDatabase();
        $database->oneRows = [[
            'Id' => 'user-1',
            'Fullname' => 'Socio',
        ]];

        $user = (new DesktopUserProfileRepository($database))->findUser(
            'customer-1',
            'user-1',
        );

        self::assertSame('user-1', $user['Id']);
        self::assertStringContainsString('CustomerApiId = ?', $database->queries[0]['sql']);
        self::assertSame(['user-1', 'customer-1'], $database->queries[0]['params']);
    }

    public function testRepositoryAppliesRangeAndPaginationToAttendances(): void
    {
        $database = new DesktopUserProfileDatabase();
        $database->allRows = [[]];

        (new DesktopUserProfileRepository($database))->attendances(
            'customer-1',
            'user-1',
            '2026-01-01',
            '2026-01-31',
            25,
            50,
        );

        self::assertSame(
            [
                'customer-1',
                'user-1',
                '2026-01-01 00:00:00',
                '2026-01-31 23:59:59',
                25,
                50,
            ],
            $database->queries[0]['params'],
        );
        self::assertStringContainsString('LIMIT ? OFFSET ?', $database->queries[0]['sql']);
    }

    public function testPolicyNormalizesSubscriptionValueTypes(): void
    {
        $subscription = (new DesktopUserProfileQueryPolicy())->normalizeSubscription([
            'Payment' => '123.45',
            'SubscriptionPeriodDays' => '30',
            'Warning' => '1',
            'Finished' => '0',
            'Registered' => null,
        ]);

        self::assertSame(123.45, $subscription['Payment']);
        self::assertSame(30, $subscription['SubscriptionPeriodDays']);
        self::assertTrue($subscription['Warning']);
        self::assertFalse($subscription['Finished']);
        self::assertNull($subscription['Registered']);
    }

    public function testPolicyRejectsAnImpossibleCalendarDate(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('from debe ser una fecha YYYY-MM-DD');

        (new DesktopUserProfileQueryPolicy())->range([
            'from' => '2026-02-30',
        ]);
    }

    public function testPolicyBuildsDailyWeekdayAndHourlySeries(): void
    {
        $statistics = (new DesktopUserProfileQueryPolicy())->statistics(
            'user-1',
            '2026-02-01',
            '2026-02-02',
            [
                'totalAttempts' => '3',
                'allowedAttempts' => '2',
                'deniedAttempts' => '1',
                'daysWithAllowedAccess' => '2',
            ],
            [
                [
                    'date' => '2026-02-01',
                    'hour' => '18',
                    'weekday' => '1',
                    'Active' => '0',
                ],
                [
                    'date' => '2026-02-02',
                    'hour' => '9',
                    'weekday' => '2',
                    'Active' => '1',
                ],
            ],
        );

        self::assertSame(3, $statistics['summary']['totalAttempts']);
        self::assertSame(1, $statistics['daily'][0]['denied']);
        self::assertSame(1, $statistics['byWeekday'][1]['total']);
        self::assertSame(1, $statistics['byHour'][9]['total']);
    }
}
