<?php

declare(strict_types=1);

namespace App\Modules\CustomerStats\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\CustomerStats\Contracts\ICustomerStatsRepository;

final readonly class CustomerStatsRepository implements ICustomerStatsRepository
{
    public function __construct(private IDatabaseConnection $db)
    {
    }

    public function global(): array
    {
        return [
            'totalCustomers' => $this->count('SELECT COUNT(*) total FROM Customers'),
            'activeCustomers' => $this->count('SELECT COUNT(*) total FROM Customers WHERE IsActive=1'),
            'totalUsers' => $this->count(
                'SELECT COUNT(*) total FROM UsersDesktop WHERE Removed=0 OR Removed IS NULL',
            ),
            'totalSubscriptions' => $this->count(
                'SELECT COUNT(*) total FROM SubscriptionsDesktop WHERE Removed=0 OR Removed IS NULL',
            ),
            'totalActiveSubscriptions' => $this->count(
                'SELECT COUNT(*) total FROM ViewSubscriptions WHERE Expiration>0',
            ),
            'totalProducts' => $this->count(
                'SELECT COUNT(*) total FROM ProductDesktop WHERE IsDeleted=0 OR IsDeleted IS NULL',
            ),
            'totalAttendances' => $this->count(
                'SELECT COUNT(*) total FROM AttendancesDesktop WHERE Removed=0 OR Removed IS NULL',
            ),
            'todayAttendances' => $this->count(
                'SELECT COUNT(*) total FROM AttendancesDesktop '
                . 'WHERE (Removed=0 OR Removed IS NULL) AND DATE(CheckIn)=CURDATE()',
            ),
            'monthlyMessages' => $this->count(
                'SELECT COUNT(*) total FROM MessageSent '
                . 'WHERE Successful=1 AND IsDebug=0 '
                . 'AND MONTH(DateSent)=MONTH(CURDATE()) '
                . 'AND YEAR(DateSent)=YEAR(CURDATE())',
            ),
        ];
    }
    public function all(): array
    {
        try {
            $rows = $this->db->fetchAll('SELECT Id,Name,Email,PlanCode,IsActive,LastSeen,CreatedAt FROM Customers ORDER BY Name ASC');
        } catch (\Throwable $e) {
            error_log('CustomerStatsRepository all error: ' . $e->getMessage());
            return [];
        }

        return array_map(
            fn (array $customer): array => [
                'customer' => [
                    'customerId' => $customer['Id'] ?? '',
                    'name' => $customer['Name'] ?? '',
                    'email' => $customer['Email'] ?? '',
                    'planCode' => $customer['PlanCode'] ?? null,
                    'isActive' => (bool)($customer['IsActive'] ?? 1),
                    'lastSeen' => $customer['LastSeen'] ?? null,
                    'createdAt' => $customer['CreatedAt'] ?? null,
                ],
                'stats' => $this->forCustomer((string)($customer['Id'] ?? '')),
            ],
            $rows,
        );
    }
    public function forCustomer(string $id): array
    {
        if (trim($id) === '') {
            return $this->empty();
        }

        return [
            'customerApiId' => $id,
            'users' => $this->count(
                'SELECT COUNT(*) total FROM UsersDesktop '
                . 'WHERE CustomerApiId=? AND (Removed=0 OR Removed IS NULL)',
                [$id],
            ),
            'activeSubscriptions' => $this->count(
                'SELECT COUNT(*) total FROM ViewSubscriptions '
                . 'WHERE CustomerApiId=? AND Expiration>0',
                [$id],
            ),
            'totalSubscriptions' => $this->count(
                'SELECT COUNT(*) total FROM SubscriptionsDesktop '
                . 'WHERE CustomerApiId=? AND (Removed=0 OR Removed IS NULL)',
                [$id],
            ),
            'products' => $this->count(
                'SELECT COUNT(*) total FROM ProductDesktop '
                . 'WHERE CustomerApiId=? AND (IsDeleted=0 OR IsDeleted IS NULL)',
                [$id],
            ),
            'attendances' => $this->count(
                'SELECT COUNT(*) total FROM AttendancesDesktop '
                . 'WHERE CustomerApiId=? AND (Removed=0 OR Removed IS NULL)',
                [$id],
            ),
            'todayAttendances' => $this->count(
                'SELECT COUNT(*) total FROM AttendancesDesktop '
                . 'WHERE CustomerApiId=? AND (Removed=0 OR Removed IS NULL) '
                . 'AND DATE(CheckIn)=CURDATE()',
                [$id],
            ),
            'monthlyAttendances' => $this->count(
                'SELECT COUNT(*) total FROM AttendancesDesktop '
                . 'WHERE CustomerApiId=? AND (Removed=0 OR Removed IS NULL) '
                . 'AND MONTH(CheckIn)=MONTH(CURDATE()) '
                . 'AND YEAR(CheckIn)=YEAR(CURDATE())',
                [$id],
            ),
            'messagesSentThisMonth' => $this->count(
                'SELECT COUNT(*) total FROM MessageSent '
                . 'WHERE CustomerApiId=? AND Successful=1 AND IsDebug=0 '
                . 'AND MONTH(DateSent)=MONTH(CURDATE()) '
                . 'AND YEAR(DateSent)=YEAR(CURDATE())',
                [$id],
            ),
        ];
    }
    public function findCustomer(string $id): ?array
    {
        $r = $this->db->fetchOne('SELECT * FROM Customers WHERE Id=?', [$id]);
        if (!$r) {
            return null;
        }

        return [
            'customerId' => $r['Id'],
            'billingId' => $r['BillingId'] ?? null,
            'planCode' => $r['PlanCode'] ?? null,
            'name' => $r['Name'],
            'codeAccess' => $r['CodeAccess'] ?? null,
            'email' => $r['Email'],
            'phone' => $r['Phone'],
            'deviceName' => $r['DeviceName'],
            'token' => $r['Token'],
            'isActive' => (bool)($r['IsActive'] ?? 1),
        ];
    }
    private function count(string $sql, array $params = []): int
    {
        try {
            $r = $this->db->fetchOne($sql, $params);
            return (int)($r['total'] ?? 0);
        } catch (\Throwable $e) {
            error_log('CustomerStatsRepository query error: ' . $e->getMessage());
            return 0;
        }
    }
    private function empty(): array
    {
        return [
            'customerApiId' => null,
            'users' => 0,
            'activeSubscriptions' => 0,
            'totalSubscriptions' => 0,
            'products' => 0,
            'attendances' => 0,
            'todayAttendances' => 0,
            'monthlyAttendances' => 0,
            'messagesSentThisMonth' => 0,
        ];
    }
}
