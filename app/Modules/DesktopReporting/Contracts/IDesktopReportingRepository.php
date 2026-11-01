<?php

declare(strict_types=1);

namespace App\Modules\DesktopReporting\Contracts;

interface IDesktopReportingRepository
{
    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function dashboard(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function users(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function memberships(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function membershipSales(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function products(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function attendances(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function sales(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function admins(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function chartsOverview(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function chartsUsers(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function chartsMemberships(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function chartsProducts(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function chartsAttendances(string $customerId, array $query): array;

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function chartsSales(string $customerId, array $query): array;
}
