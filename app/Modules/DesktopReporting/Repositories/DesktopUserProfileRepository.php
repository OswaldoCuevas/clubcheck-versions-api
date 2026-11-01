<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\DesktopReporting\Contracts\IDesktopUserProfileRepository;

final readonly class DesktopUserProfileRepository implements IDesktopUserProfileRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function findUser(string $customerId, string $userId): ?array
    {
        return $this->database->fetchOne(
            'SELECT Id, Fullname, PhoneNumber, PhoneNumberEmergency,
                    Gender, BirthDate, Code, Email, CreatedOn
             FROM UsersDesktop
             WHERE Id = ? AND CustomerApiId = ? AND COALESCE(Removed, 0) = 0',
            [$userId, $customerId],
        );
    }

    public function photo(string $customerId, string $userId): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT p.Version, p.UpdatedAt
             FROM UserPhotosDesktop p
             JOIN UsersDesktop u
               ON u.Id = p.UserId AND u.CustomerApiId = p.CustomerApiId
             WHERE p.CustomerApiId = ? AND p.UserId = ?
               AND COALESCE(u.Removed, 0) = 0
             LIMIT 1',
            [$customerId, $userId],
        );

        return $row === null
            ? null
            : [
                'version' => (int) $row['Version'],
                'updatedAt' => $row['UpdatedAt'],
            ];
    }

    public function currentSubscription(string $customerId, string $userId): ?array
    {
        $end = $this->normalizedDate('s.EndingDate');
        $day = "LEFT({$end}, 10)";
        $validDay = "CASE
            WHEN {$day} REGEXP '^[1-9][0-9]{3}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$'
            THEN CASE
                WHEN CAST(RIGHT({$day}, 2) AS UNSIGNED)
                    <= DAY(LAST_DAY(CONCAT(LEFT({$day}, 7), '-01')))
                THEN {$day}
            END
        END";

        return $this->database->fetchOne(
            "SELECT {$this->subscriptionFields()}
             FROM {$this->subscriptionTable()}
             WHERE s.CustomerApiId = ? AND s.UserId = ?
               AND COALESCE(s.Removed, 0) = 0
               AND ({$validDay}) IS NOT NULL
             ORDER BY {$day} DESC, s.Id DESC
             LIMIT 1",
            [$customerId, $userId],
        );
    }

    public function countAttendances(
        string $customerId,
        string $userId,
        ?string $from,
        ?string $to,
    ): int {
        [$where, $parameters] = $this->historyFilter(
            '',
            "STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')",
            $customerId,
            $userId,
            $from,
            $to,
        );
        $row = $this->database->fetchOne(
            "SELECT COUNT(*) AS total FROM AttendancesDesktop WHERE {$where}",
            $parameters,
        );

        return (int) ($row['total'] ?? 0);
    }

    public function attendances(
        string $customerId,
        string $userId,
        ?string $from,
        ?string $to,
        int $limit,
        int $offset,
    ): array {
        $date = "STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')";
        [$where, $parameters] = $this->historyFilter(
            '',
            $date,
            $customerId,
            $userId,
            $from,
            $to,
        );

        return $this->database->fetchAll(
            "SELECT Id, UserId, CheckIn, Active
             FROM AttendancesDesktop
             WHERE {$where}
             ORDER BY {$date} DESC, Id DESC
             LIMIT ? OFFSET ?",
            [...$parameters, $limit, $offset],
        );
    }

    public function countSubscriptions(
        string $customerId,
        string $userId,
        ?string $from,
        ?string $to,
    ): int {
        $date = "STR_TO_DATE(LEFT({$this->normalizedDate('s.StartDate')}, 10), '%Y-%m-%d')";
        [$where, $parameters] = $this->historyFilter(
            's.',
            $date,
            $customerId,
            $userId,
            $from,
            $to,
        );
        $row = $this->database->fetchOne(
            "SELECT COUNT(*) AS total
             FROM {$this->subscriptionTable()}
             WHERE {$where}",
            $parameters,
        );

        return (int) ($row['total'] ?? 0);
    }

    public function subscriptions(
        string $customerId,
        string $userId,
        ?string $from,
        ?string $to,
        int $limit,
        int $offset,
    ): array {
        $date = "STR_TO_DATE(LEFT({$this->normalizedDate('s.StartDate')}, 10), '%Y-%m-%d')";
        [$where, $parameters] = $this->historyFilter(
            's.',
            $date,
            $customerId,
            $userId,
            $from,
            $to,
        );

        return $this->database->fetchAll(
            "SELECT {$this->subscriptionFields()}
             FROM {$this->subscriptionTable()}
             WHERE {$where}
             ORDER BY {$date} DESC, s.Id DESC
             LIMIT ? OFFSET ?",
            [...$parameters, $limit, $offset],
        );
    }

    public function attendanceSummary(
        string $customerId,
        string $userId,
        string $from,
        string $to,
    ): array {
        $date = "STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')";

        return $this->database->fetchOne(
            "SELECT COUNT(*) AS totalAttempts,
                    COALESCE(SUM(a.Active = 1), 0) AS allowedAttempts,
                    COALESCE(SUM(a.Active = 0), 0) AS deniedAttempts,
                    COUNT(DISTINCT CASE
                        WHEN a.Active = 1 THEN DATE({$date})
                    END) AS daysWithAllowedAccess
             FROM AttendancesDesktop a
             WHERE a.CustomerApiId = ? AND a.UserId = ?
               AND COALESCE(a.Removed, 0) = 0
               AND {$date} BETWEEN ? AND ?",
            [$customerId, $userId, $from, $to],
        ) ?? [];
    }

    public function latestDailyAttendances(
        string $customerId,
        string $userId,
        string $from,
        string $to,
    ): array {
        $date = "STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')";
        $latest = "NOT EXISTS (
            SELECT 1
            FROM AttendancesDesktop ax
            WHERE ax.CustomerApiId = a.CustomerApiId
              AND ax.UserId = a.UserId
              AND COALESCE(ax.Removed, 0) = 0
              AND DATE(STR_TO_DATE(ax.CheckIn, '%Y-%m-%d %H:%i:%s')) = DATE({$date})
              AND (
                  STR_TO_DATE(ax.CheckIn, '%Y-%m-%d %H:%i:%s') > {$date}
                  OR (
                      STR_TO_DATE(ax.CheckIn, '%Y-%m-%d %H:%i:%s') = {$date}
                      AND ax.Id > a.Id
                  )
              )
        )";

        return $this->database->fetchAll(
            "SELECT DATE({$date}) AS date,
                    HOUR({$date}) AS hour,
                    DAYOFWEEK({$date}) AS weekday,
                    a.Active
             FROM AttendancesDesktop a
             WHERE a.CustomerApiId = ? AND a.UserId = ?
               AND COALESCE(a.Removed, 0) = 0
               AND {$date} BETWEEN ? AND ?
               AND {$latest}
             ORDER BY {$date} ASC",
            [$customerId, $userId, $from, $to],
        );
    }

    private function subscriptionTable(): string
    {
        return 'SubscriptionsDesktop s
            LEFT JOIN SubscriptionPeriodDesktop p
              ON p.Id = s.SubscriptionPeriodId
             AND p.CustomerApiId = s.CustomerApiId
             AND p.IsDeleted = 0';
    }

    private function subscriptionFields(): string
    {
        $start = $this->normalizedDate('s.StartDate');
        $end = $this->normalizedDate('s.EndingDate');

        return "s.Id, s.UserId, {$start} AS StartDate, {$end} AS EndingDate,
                s.SubscriptionPeriodId, s.Payment, s.Warning, s.Finished, s.Registered,
                p.Name AS SubscriptionPeriodName,
                p.Days AS SubscriptionPeriodDays";
    }

    private function normalizedDate(string $column): string
    {
        return "NULLIF(TRIM(REPLACE(REPLACE({$column}, ',', ''), ';', ':')), '')";
    }

    private function historyFilter(
        string $alias,
        string $date,
        string $customerId,
        string $userId,
        ?string $from,
        ?string $to,
    ): array {
        $where = "{$alias}CustomerApiId = ?
            AND {$alias}UserId = ?
            AND COALESCE({$alias}Removed, 0) = 0";
        $parameters = [$customerId, $userId];

        if ($from !== null) {
            $where .= " AND {$date} >= ?";
            $parameters[] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $where .= " AND {$date} <= ?";
            $parameters[] = $to . ' 23:59:59';
        }

        return [$where, $parameters];
    }
}
