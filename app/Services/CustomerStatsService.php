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
     * Ejecuta una consulta de conteo de forma segura.
     * Si la tabla o columna no existe, devuelve 0.
     */
    private function safeCount(string $sql, array $params = []): int
    {
        try {
            $row = $this->db->fetchOne($sql, $params);
            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            error_log('CustomerStatsService query error: ' . $e->getMessage());
            return 0;
        }
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
        try {
            $customers = $this->db->fetchAll('SELECT Id, Name, Email, PlanCode, IsActive, LastSeen, CreatedAt FROM Customers ORDER BY Name ASC');
        } catch (\Throwable $e) {
            error_log('CustomerStatsService getAllCustomersStats error: ' . $e->getMessage());
            return [];
        }
        
        if (!is_array($customers)) {
            return [];
        }
        
        $result = [];
        foreach ($customers as $customer) {
            $customerApiId = $customer['Id'] ?? '';
            $stats = $this->getCustomerStats($customerApiId);
            
            $result[] = [
                'customer' => [
                    'customerId' => $customer['Id'] ?? '',
                    'name' => $customer['Name'] ?? '',
                    'email' => $customer['Email'] ?? '',
                    'planCode' => $customer['PlanCode'] ?? null,
                    'isActive' => (bool) ($customer['IsActive'] ?? 1),
                    'lastSeen' => $customer['LastSeen'] ?? null,
                    'createdAt' => $customer['CreatedAt'] ?? null,
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
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM UsersDesktop WHERE CustomerApiId = ? AND (Removed = 0 OR Removed IS NULL)',
            [$customerApiId]
        );
    }

    private function countActiveSubscriptions(string $customerApiId): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM SubscriptionsDesktop 
             WHERE CustomerApiId = ? 
             AND (Removed = 0 OR Removed IS NULL) 
             AND (Finished = 0 OR Finished IS NULL)
             AND (EndingDate IS NULL OR EndingDate >= CURDATE())',
            [$customerApiId]
        );
    }

    private function countTotalSubscriptions(string $customerApiId): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM SubscriptionsDesktop WHERE CustomerApiId = ? AND (Removed = 0 OR Removed IS NULL)',
            [$customerApiId]
        );
    }

    private function countProducts(string $customerApiId): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM ProductDesktop WHERE CustomerApiId = ? AND (IsDeleted = 0 OR IsDeleted IS NULL)',
            [$customerApiId]
        );
    }

    private function countAttendances(string $customerApiId): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM AttendancesDesktop WHERE CustomerApiId = ? AND (Removed = 0 OR Removed IS NULL)',
            [$customerApiId]
        );
    }

    private function countTodayAttendances(string $customerApiId): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM AttendancesDesktop 
             WHERE CustomerApiId = ? 
             AND (Removed = 0 OR Removed IS NULL) 
             AND DATE(CheckIn) = CURDATE()',
            [$customerApiId]
        );
    }

    private function countMonthlyAttendances(string $customerApiId): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM AttendancesDesktop 
             WHERE CustomerApiId = ? 
             AND (Removed = 0 OR Removed IS NULL) 
             AND MONTH(CheckIn) = MONTH(CURDATE()) 
             AND YEAR(CheckIn) = YEAR(CURDATE())',
            [$customerApiId]
        );
    }

    private function countMessagesSentThisMonth(string $customerApiId): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM MessageSent 
             WHERE CustomerApiId = ? 
             AND Successful = 1 
             AND MONTH(DateSent) = MONTH(CURDATE()) 
             AND YEAR(DateSent) = YEAR(CURDATE())',
            [$customerApiId]
        );
    }

    // =============================================
    // Métodos de conteo global
    // =============================================

    private function countTotalCustomers(): int
    {
        return $this->safeCount('SELECT COUNT(*) AS total FROM Customers');
    }

    private function countActiveCustomers(): int
    {
        return $this->safeCount('SELECT COUNT(*) AS total FROM Customers WHERE IsActive = 1');
    }

    private function countAllUsers(): int
    {
        return $this->safeCount('SELECT COUNT(*) AS total FROM UsersDesktop WHERE Removed = 0 OR Removed IS NULL');
    }

    private function countAllSubscriptions(): int
    {
        return $this->safeCount('SELECT COUNT(*) AS total FROM SubscriptionsDesktop WHERE Removed = 0 OR Removed IS NULL');
    }

    private function countAllActiveSubscriptions(): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM SubscriptionsDesktop 
             WHERE (Removed = 0 OR Removed IS NULL) 
             AND (Finished = 0 OR Finished IS NULL)
             AND (EndingDate IS NULL OR EndingDate >= CURDATE())'
        );
    }

    private function countAllProducts(): int
    {
        return $this->safeCount('SELECT COUNT(*) AS total FROM ProductDesktop WHERE IsDeleted = 0 OR IsDeleted IS NULL');
    }

    private function countAllAttendances(): int
    {
        return $this->safeCount('SELECT COUNT(*) AS total FROM AttendancesDesktop WHERE Removed = 0 OR Removed IS NULL');
    }

    private function countAllTodayAttendances(): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM AttendancesDesktop 
             WHERE (Removed = 0 OR Removed IS NULL) 
             AND DATE(CheckIn) = CURDATE()'
        );
    }

    private function countAllMonthlyMessages(): int
    {
        return $this->safeCount(
            'SELECT COUNT(*) AS total FROM MessageSent 
             WHERE Successful = 1 
             AND MONTH(DateSent) = MONTH(CURDATE()) 
             AND YEAR(DateSent) = YEAR(CURDATE())'
        );
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
