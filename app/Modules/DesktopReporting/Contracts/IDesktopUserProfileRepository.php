<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Contracts;

interface IDesktopUserProfileRepository
{
    public function findUser(string $customerId, string $userId): ?array;

    public function photo(string $customerId, string $userId): ?array;

    public function currentSubscription(string $customerId, string $userId): ?array;

    public function countAttendances(
        string $customerId,
        string $userId,
        ?string $from,
        ?string $to,
    ): int;

    public function attendances(
        string $customerId,
        string $userId,
        ?string $from,
        ?string $to,
        int $limit,
        int $offset,
    ): array;

    public function countSubscriptions(
        string $customerId,
        string $userId,
        ?string $from,
        ?string $to,
    ): int;

    public function subscriptions(
        string $customerId,
        string $userId,
        ?string $from,
        ?string $to,
        int $limit,
        int $offset,
    ): array;

    public function attendanceSummary(
        string $customerId,
        string $userId,
        string $from,
        string $to,
    ): array;

    public function latestDailyAttendances(
        string $customerId,
        string $userId,
        string $from,
        string $to,
    ): array;
}
