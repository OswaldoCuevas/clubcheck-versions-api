<?php

namespace App\Services;

require_once __DIR__ . '/../Core/Model.php';
require_once __DIR__ . '/../Models/SystemSettingModel.php';

use Core\Model;
use Models\SystemSettingModel;

class AdminDashboardService extends Model
{
    private const WHATSAPP_COST_KEY = 'whatsapp_message_unit_cost_mxn';

    protected function initialize(): void
    {
        // Nada adicional requerido.
    }

    public function getDashboard(?StripeService $stripeService = null): array
    {
        $messageCost = $this->getWhatsappMessageCost();
        $messages = $this->getWhatsappMessageStats($messageCost);
        $stripe = $stripeService ? $stripeService->getMonthlyBillingSummary() : $this->emptyStripeSummary();

        return [
            'customers' => $this->getCustomerStats(),
            'whatsapp' => $messages,
            'stripe' => $stripe,
            'storage' => $this->getTableStorageStats(),
            'settings' => [
                'whatsapp_message_unit_cost_mxn' => $messageCost,
            ],
            'generatedAt' => date('Y-m-d H:i:s'),
        ];
    }

    public function updateWhatsappMessageCost(float $cost): void
    {
        if ($cost < 0) {
            throw new \InvalidArgumentException('El costo por mensaje no puede ser negativo');
        }

        $settings = new SystemSettingModel();
        $settings->set(
            self::WHATSAPP_COST_KEY,
            number_format($cost, 6, '.', ''),
            'Costo aproximado por mensaje exitoso de WhatsApp en MXN'
        );
    }

    private function getWhatsappMessageCost(): float
    {
        try {
            $settings = new SystemSettingModel();
            return (float)$settings->get(self::WHATSAPP_COST_KEY, '0.00');
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function getCustomerStats(): array
    {
        return [
            'total' => $this->safeInt('SELECT COUNT(*) AS total FROM Customers'),
            'active' => $this->safeInt('SELECT COUNT(*) AS total FROM Customers WHERE IsActive = 1'),
            'withBillingId' => $this->safeInt("SELECT COUNT(*) AS total FROM Customers WHERE BillingId IS NOT NULL AND BillingId <> ''"),
        ];
    }

    private function getWhatsappMessageStats(float $unitCost): array
    {
        $table = $this->resolveMessageTable();
        if ($table === null) {
            return [
                'table' => null,
                'currentMonthMessages' => 0,
                'unitCost' => $unitCost,
                'currentMonthCost' => 0,
                'months' => [],
            ];
        }

        $currentMonthMessages = $this->safeInt(
            "SELECT COUNT(*) AS total
             FROM {$table}
             WHERE Successful = 1
             AND DateSent >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
             AND DateSent < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)"
        );

        $months = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT DATE_FORMAT(DateSent, '%Y-%m') AS month_key, COUNT(*) AS messages
                 FROM {$table}
                 WHERE Successful = 1
                 AND DateSent >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
                 GROUP BY DATE_FORMAT(DateSent, '%Y-%m')
                 ORDER BY month_key ASC"
            );

            foreach ($rows as $row) {
                $count = (int)($row['messages'] ?? 0);
                $months[] = [
                    'month' => $row['month_key'],
                    'messages' => $count,
                    'cost' => round($count * $unitCost, 2),
                ];
            }
        } catch (\Throwable $e) {
            error_log('AdminDashboardService whatsapp months error: ' . $e->getMessage());
        }

        return [
            'table' => $table,
            'currentMonthMessages' => $currentMonthMessages,
            'unitCost' => $unitCost,
            'currentMonthCost' => round($currentMonthMessages * $unitCost, 2),
            'months' => $months,
        ];
    }

    private function getTableStorageStats(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT
                    TABLE_NAME AS table_name,
                    TABLE_ROWS AS table_rows,
                    DATA_LENGTH AS data_bytes,
                    INDEX_LENGTH AS index_bytes,
                    (DATA_LENGTH + INDEX_LENGTH) AS total_bytes
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_TYPE = 'BASE TABLE'
                 ORDER BY total_bytes DESC
                 LIMIT 12"
            );

            $totalBytes = 0;
            $tables = [];
            foreach ($rows as $row) {
                $bytes = (int)($row['total_bytes'] ?? 0);
                $totalBytes += $bytes;
                $tables[] = [
                    'name' => $row['table_name'],
                    'rows' => (int)($row['table_rows'] ?? 0),
                    'bytes' => $bytes,
                    'mb' => round($bytes / 1024 / 1024, 2),
                ];
            }

            return [
                'totalBytes' => $totalBytes,
                'totalMb' => round($totalBytes / 1024 / 1024, 2),
                'tables' => $tables,
            ];
        } catch (\Throwable $e) {
            error_log('AdminDashboardService storage error: ' . $e->getMessage());
            return [
                'totalBytes' => 0,
                'totalMb' => 0,
                'tables' => [],
            ];
        }
    }

    private function resolveMessageTable(): ?string
    {
        foreach (['MessageSent', 'SentMessagesDesktop'] as $table) {
            try {
                if ($this->db->fetchOne("SHOW TABLES LIKE '{$table}'") !== null) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Probar siguiente tabla.
            }
        }

        return null;
    }

    private function safeInt(string $sql, array $params = []): int
    {
        try {
            $row = $this->db->fetchOne($sql, $params);
            return (int)($row['total'] ?? 0);
        } catch (\Throwable $e) {
            error_log('AdminDashboardService query error: ' . $e->getMessage());
            return 0;
        }
    }

    private function emptyStripeSummary(): array
    {
        return [
            'success' => false,
            'receivedThisMonth' => ['amount' => 0, 'currency' => 'mxn'],
            'expectedThisMonth' => ['amount' => 0, 'currency' => 'mxn', 'subscriptions' => 0],
            'error' => 'Stripe no disponible',
        ];
    }
}
