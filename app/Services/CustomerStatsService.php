<?php

namespace App\Services;

use Core\Model;

require_once __DIR__ . '/../Core/Model.php';

/**
 * Servicio para obtener estadísticas de clientes desde las tablas desktop.
 * 
 * Uso:
 *   $service = new CustomerStatsService();
 *   $stats = $service->getCustomerStats('customer-id-123');
 *   $allStats = $service->getAllCustomersStats();
 */
class CustomerStatsService extends Model
{
    protected function initialize()
    {
        // No se requiere inicialización adicional
    }

    /**
     * Obtiene estadísticas de un cliente específico.
     */
    public function getCustomerStats(string $customerApiId): array
    {
        $customerApiId = trim($customerApiId);
        
        if ($customerApiId === '') {
            return $this->emptyStats();
        }

        return [
            'customerApiId' => $customerApiId,
            'users' => $this->countUsers($customerApiId),
            'activeSubscriptions' => $this->countActiveSubscriptions($customerApiId),
            'totalSubscriptions' => $this->countTotalSubscriptions($customerApiId),
            'products' => $this->countProducts($customerApiId),
            'attendances' => $this->countAttendances($customerApiId),
            'todayAttendances' => $this->countTodayAttendances($customerApiId),
            'monthlyAttendances' => $this->countMonthlyAttendances($customerApiId),
            'messagesSentThisMonth' => $this->countMessagesSentThisMonth($customerApiId),
        ];
    }

    /**
     * Obtiene estadísticas de todos los clientes registrados.
     */
    public function getAllCustomersStats(): array
    {
        $customers = $this->db->fetchAll('SELECT Id, Name, Email, PlanCode, IsActive, LastSeen, CreatedAt FROM Customers ORDER BY Name ASC');
        
        $result = [];
        foreach ($customers as $customer) {
            $customerApiId = $customer['Id'];
            $stats = $this->getCustomerStats($customerApiId);
            
            $result[] = [
                'customer' => [
                    'customerId' => $customer['Id'],
                    'name' => $customer['Name'],
                    'email' => $customer['Email'],
                    'planCode' => $customer['PlanCode'],
                    'isActive' => (bool) ($customer['IsActive'] ?? 1),
                    'lastSeen' => $customer['LastSeen'],
                    'createdAt' => $customer['CreatedAt'],
                ],
                'stats' => $stats,
            ];
        }

        return $result;
    }

    /**
     * Obtiene resumen global de estadísticas.
     */
    public function getGlobalStats(): array
    {
        return [
            'totalCustomers' => $this->countTotalCustomers(),
            'activeCustomers' => $this->countActiveCustomers(),
            'totalUsers' => $this->countAllUsers(),
            'totalSubscriptions' => $this->countAllSubscriptions(),
            'totalActiveSubscriptions' => $this->countAllActiveSubscriptions(),
            'totalProducts' => $this->countAllProducts(),
            'totalAttendances' => $this->countAllAttendances(),
            'todayAttendances' => $this->countAllTodayAttendances(),
            'monthlyMessages' => $this->countAllMonthlyMessages(),
        ];
    }

    // =============================================
    // Métodos de conteo por cliente
    // =============================================

    private function countUsers(string $customerApiId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM usersdesktop WHERE CustomerApiId = ? AND (Removed = 0 OR Removed IS NULL)',
            [$customerApiId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countActiveSubscriptions(string $customerApiId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM subscriptionsdesktop 
             WHERE CustomerApiId = ? 
             AND (Removed = 0 OR Removed IS NULL) 
             AND (Finished = 0 OR Finished IS NULL)
             AND (EndingDate IS NULL OR EndingDate >= CURDATE())',
            [$customerApiId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countTotalSubscriptions(string $customerApiId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM subscriptionsdesktop WHERE CustomerApiId = ? AND (Removed = 0 OR Removed IS NULL)',
            [$customerApiId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countProducts(string $customerApiId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM productdesktop WHERE CustomerApiId = ? AND (IsDeleted = 0 OR IsDeleted IS NULL)',
            [$customerApiId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countAttendances(string $customerApiId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM attendancesdesktop WHERE CustomerApiId = ? AND (Removed = 0 OR Removed IS NULL)',
            [$customerApiId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countTodayAttendances(string $customerApiId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM attendancesdesktop 
             WHERE CustomerApiId = ? 
             AND (Removed = 0 OR Removed IS NULL) 
             AND DATE(CheckIn) = CURDATE()',
            [$customerApiId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countMonthlyAttendances(string $customerApiId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM attendancesdesktop 
             WHERE CustomerApiId = ? 
             AND (Removed = 0 OR Removed IS NULL) 
             AND MONTH(CheckIn) = MONTH(CURDATE()) 
             AND YEAR(CheckIn) = YEAR(CURDATE())',
            [$customerApiId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countMessagesSentThisMonth(string $customerApiId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM MessageSent 
             WHERE CustomerApiId = ? 
             AND Successful = 1 
             AND MONTH(DateSent) = MONTH(CURDATE()) 
             AND YEAR(DateSent) = YEAR(CURDATE())',
            [$customerApiId]
        );
        return (int) ($row['total'] ?? 0);
    }

    // =============================================
    // Métodos de conteo global
    // =============================================

    private function countTotalCustomers(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM Customers');
        return (int) ($row['total'] ?? 0);
    }

    private function countActiveCustomers(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM Customers WHERE IsActive = 1');
        return (int) ($row['total'] ?? 0);
    }

    private function countAllUsers(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM usersdesktop WHERE Removed = 0 OR Removed IS NULL');
        return (int) ($row['total'] ?? 0);
    }

    private function countAllSubscriptions(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM subscriptionsdesktop WHERE Removed = 0 OR Removed IS NULL');
        return (int) ($row['total'] ?? 0);
    }

    private function countAllActiveSubscriptions(): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM subscriptionsdesktop 
             WHERE (Removed = 0 OR Removed IS NULL) 
             AND (Finished = 0 OR Finished IS NULL)
             AND (EndingDate IS NULL OR EndingDate >= CURDATE())'
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countAllProducts(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM productdesktop WHERE IsDeleted = 0 OR IsDeleted IS NULL');
        return (int) ($row['total'] ?? 0);
    }

    private function countAllAttendances(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM attendancesdesktop WHERE Removed = 0 OR Removed IS NULL');
        return (int) ($row['total'] ?? 0);
    }

    private function countAllTodayAttendances(): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM attendancesdesktop 
             WHERE (Removed = 0 OR Removed IS NULL) 
             AND DATE(CheckIn) = CURDATE()'
        );
        return (int) ($row['total'] ?? 0);
    }

    private function countAllMonthlyMessages(): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM MessageSent 
             WHERE Successful = 1 
             AND MONTH(DateSent) = MONTH(CURDATE()) 
             AND YEAR(DateSent) = YEAR(CURDATE())'
        );
        return (int) ($row['total'] ?? 0);
    }

    private function emptyStats(): array
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
