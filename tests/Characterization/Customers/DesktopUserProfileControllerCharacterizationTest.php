<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use Tests\Characterization\Support\DesktopHttpTestCase;

final class DesktopUserProfileControllerCharacterizationTest extends DesktopHttpTestCase
{
    private string $userId = 'desktop-profile-user';

    protected function setUp(): void
    {
        parent::setUp();

        $database = $this->database->connection();
        $database->execute_query(
            'INSERT INTO UsersDesktop
                (Id, CustomerApiId, Fullname, PhoneNumber, PhoneNumberEmergency,
                 Gender, BirthDate, Code, Email, CreatedOn, Removed)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->userId,
                $this->customerId,
                'Socio de perfil',
                '6140000000',
                '6140000001',
                'M',
                '1990-01-02',
                'SOCIO-1',
                'socio@example.test',
                '2026-01-01 10:00:00',
                0,
            ],
        );
        $database->execute_query(
            'INSERT INTO SubscriptionPeriodDesktop
                (Id, CustomerApiId, Name, Days, Price, CreatedOn, IsDeleted)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                'desktop-period-1',
                $this->customerId,
                'Mensual',
                30,
                500,
                '2026-01-01 00:00:00',
                0,
            ],
        );
        $database->execute_query(
            'INSERT INTO SubscriptionsDesktop
                (Id, CustomerApiId, StartDate, EndingDate, Removed, UserId,
                 SubscriptionPeriodId, Payment, Warning, Finished, Registered)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?),
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                'desktop-subscription-1',
                $this->customerId,
                '2026-01-01',
                '2026-01-31',
                0,
                $this->userId,
                'desktop-period-1',
                500,
                0,
                0,
                1,
                'desktop-subscription-2',
                $this->customerId,
                '2026-02-01',
                '2026-02-28',
                0,
                $this->userId,
                'desktop-period-1',
                550,
                1,
                0,
                1,
            ],
        );
        $database->execute_query(
            'INSERT INTO AttendancesDesktop
                (Id, CustomerApiId, CheckIn, Removed, UserId, Active)
             VALUES
                (?, ?, ?, ?, ?, ?),
                (?, ?, ?, ?, ?, ?),
                (?, ?, ?, ?, ?, ?)',
            [
                'desktop-attendance-1',
                $this->customerId,
                '2026-02-01 08:00:00',
                0,
                $this->userId,
                1,
                'desktop-attendance-2',
                $this->customerId,
                '2026-02-01 18:00:00',
                0,
                $this->userId,
                0,
                'desktop-attendance-3',
                $this->customerId,
                '2026-02-02 09:00:00',
                0,
                $this->userId,
                1,
            ],
        );
    }

    public function testUserProfilePreservesItsPublicShape(): void
    {
        $response = $this->requestAndRecord(
            'desktop-user-profile-detail',
            'GET',
            '/api/desktop/users/' . $this->userId,
            [],
            ['status' => 200],
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($this->userId, $body['Id']);
        self::assertSame('Socio de perfil', $body['Fullname']);
        self::assertNull($body['photo']);
        self::assertSame('desktop-subscription-2', $body['currentSubscription']['Id']);
        self::assertSame(550, $body['currentSubscription']['Payment']);
    }

    public function testAttendanceHistoryPreservesStablePagination(): void
    {
        $response = $this->requestAndRecord(
            'desktop-user-profile-attendances',
            'GET',
            '/api/desktop/users/' . $this->userId . '/attendances?page=1&pageSize=2',
            [],
            ['status' => 200],
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, $body['total']);
        self::assertSame(2, $body['pageSize']);
        self::assertSame(
            ['desktop-attendance-3', 'desktop-attendance-2'],
            array_column($body['items'], 'Id'),
        );
        self::assertSame([1, 0], array_column($body['items'], 'Active'));
    }

    public function testSubscriptionHistoryPreservesNormalizedTypes(): void
    {
        $response = $this->requestAndRecord(
            'desktop-user-profile-subscriptions',
            'GET',
            '/api/desktop/users/' . $this->userId . '/subscriptions',
            [],
            ['status' => 200],
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $body['total']);
        self::assertSame(550, $body['items'][0]['Payment']);
        self::assertTrue($body['items'][0]['Warning']);
        self::assertSame(30, $body['items'][0]['SubscriptionPeriodDays']);
    }

    public function testStatisticsPreserveAllAttemptsAndLatestDailySeries(): void
    {
        $response = $this->requestAndRecord(
            'desktop-user-profile-statistics',
            'GET',
            '/api/desktop/users/' . $this->userId
                . '/statistics?from=2026-02-01&to=2026-02-02',
            [],
            ['status' => 200],
        );
        $body = $this->responseJson($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [
                'totalAttempts' => 3,
                'allowedAttempts' => 2,
                'deniedAttempts' => 1,
                'daysWithAllowedAccess' => 2,
            ],
            $body['summary'],
        );
        self::assertSame('latest_attempt_per_user_per_day', $body['countMode']);
        self::assertSame(
            [
                [
                    'date' => '2026-02-01',
                    'total' => 1,
                    'allowed' => 0,
                    'denied' => 1,
                ],
                [
                    'date' => '2026-02-02',
                    'total' => 1,
                    'allowed' => 1,
                    'denied' => 0,
                ],
            ],
            $body['daily'],
        );
    }

    public function testStatisticsRequireACompleteValidRange(): void
    {
        $response = $this->requestAndRecord(
            'desktop-user-profile-statistics-range-required',
            'GET',
            '/api/desktop/users/' . $this->userId . '/statistics',
            [],
            ['status' => 422],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('from debe ser una fecha YYYY-MM-DD', $this->responseJson($response)['error']);
    }

    public function testProfileDoesNotExposeAnotherCustomerOrUnknownUser(): void
    {
        $response = $this->requestAndRecord(
            'desktop-user-profile-not-found',
            'GET',
            '/api/desktop/users/unknown-user',
            [],
            ['status' => 404],
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Socio no accesible', $this->responseJson($response)['error']);
    }
}
