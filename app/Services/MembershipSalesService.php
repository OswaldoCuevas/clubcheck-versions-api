<?php

namespace App\Services;

require_once __DIR__ . '/UserPhotoService.php';

use App\Exceptions\ApiException;

/** Historial de partidas de membresia sincronizadas; no altera vigencias ni estados sync. */
class MembershipSalesService
{
    private $db;

    /** Reutiliza la conexion del backend para consultar ventas y probarlas en persistencia temporal. */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /** Valida limites del historial; rechaza parametros ambiguos en vez de convertirlos silenciosamente. */
    private function pageNumber(array $query, string $key, int $default, int $max): int
    {
        $value = $query[$key] ?? $default;
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)
            || strlen((string) $value) > strlen((string) $max) || (int) $value > $max) {
            throw new ApiException("{$key} debe ser un entero entre 1 y {$max}", 422);
        }
        return (int) $value;
    }

    /** Normaliza fechas locales del escritorio sin conversion UTC y descarta fechas calendario imposibles. */
    private function localDate(?string $value): ?string
    {
        $value = trim(str_replace([',', ';'], ['', ':'], $value ?? ''));
        $day = substr($value, 0, 10);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        return $date && $date->format('Y-m-d') === $day && $day >= '1000-01-01' ? $value : null;
    }

    /** Consulta una partida por venta, conserva cancelaciones/bajas y limita cada relacion al gimnasio del JWT. */
    public function listing(string $customerId, array $query): array
    {
        $page = $this->pageNumber($query, 'page', 1, 1000000);
        $pageSize = $this->pageNumber($query, 'pageSize', 25, 100);
        $where = "i.CustomerApiId = ? AND NULLIF(TRIM(i.SubscriptionId), '') IS NOT NULL";
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) AS total FROM SaleTicketItemDesktop i WHERE {$where}", [$customerId])['total'];
        $rows = $this->db->fetchAll(
            "SELECT i.Id AS SaleTicketItemId, i.SaleTicketId, i.SubscriptionId,
                NULLIF(TRIM(i.ProductName), '') AS PlanName,
                t.Folio, t.SaleDate AS PaymentDate, t.PaymentMethod, NULLIF(TRIM(t.PaymentReference), '') AS PaymentReference,
                a.Id AS SellerId, a.Username AS SellerName,
                CASE WHEN t.Id IS NULL THEN NULL WHEN t.Active = 0 OR t.CancelledOn IS NOT NULL THEN 1
                    WHEN t.Active = 1 THEN 0 ELSE NULL END AS IsCancelled,
                t.CancelledOn, t.CancellationReason,
                u.Id AS UserId, u.Fullname, s.StartDate, s.EndingDate,
                i.LineTotal, t.TotalAmount, t.PaidAmount, t.ChangeAmount,
                (SELECT SUM(li.LineTotal) FROM SaleTicketItemDesktop li
                    WHERE li.SaleTicketId = t.Id AND li.CustomerApiId = i.CustomerApiId) AS ItemsTotal
             FROM SaleTicketItemDesktop i
             LEFT JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId AND t.CustomerApiId = i.CustomerApiId
             LEFT JOIN SubscriptionsDesktop s ON s.Id = i.SubscriptionId AND s.CustomerApiId = i.CustomerApiId
             LEFT JOIN UsersDesktop u ON u.Id = s.UserId AND u.CustomerApiId = i.CustomerApiId
             LEFT JOIN AdministratorsDesktop a ON a.CustomerApiId = i.CustomerApiId AND a.Id = (
                SELECT seller.Id FROM AdministratorsDesktop seller WHERE seller.CustomerApiId = i.CustomerApiId
                    AND (seller.Id = t.CreatedBy OR seller.Username = t.CreatedBy)
                ORDER BY (seller.Id = t.CreatedBy) DESC, seller.Id DESC LIMIT 1)
             WHERE {$where}
             ORDER BY t.SaleDate DESC, i.Id DESC LIMIT ? OFFSET ?",
            [$customerId, $pageSize, ($page - 1) * $pageSize]
        );
        $today = new \DateTimeImmutable('today');
        foreach ($rows as &$row) {
            $row['StartDate'] = $this->localDate($row['StartDate']);
            $row['EndingDate'] = $this->localDate($row['EndingDate']);
            $row['PaymentDate'] = $this->localDate($row['PaymentDate']);
            $row['DaysRemaining'] = $row['EndingDate'] === null ? null
                : (int) $today->diff(new \DateTimeImmutable(substr($row['EndingDate'], 0, 10)))->format('%r%a');
            $row['IsCancelled'] = $row['IsCancelled'] === null ? null : (bool) $row['IsCancelled'];
            // LineTotal ya contiene descuentos/impuestos de partida, como los reportes de ingresos.
            // Solo se llama pagado si el ticket concilia: no inventar distribuciones de descuentos globales o abonos.
            $known = $row['LineTotal'] !== null && $row['TotalAmount'] !== null && $row['ItemsTotal'] !== null
                && $row['PaidAmount'] !== null && $row['ChangeAmount'] !== null
                && abs((float) $row['ItemsTotal'] - (float) $row['TotalAmount']) < 0.005
                && abs((float) $row['PaidAmount'] - (float) $row['ChangeAmount'] - (float) $row['TotalAmount']) < 0.005;
            $row['AmountPaid'] = $known ? (float) $row['LineTotal'] : null;
            $row['AmountSource'] = $known ? 'LineTotal' : null;
            unset($row['LineTotal'], $row['TotalAmount'], $row['PaidAmount'], $row['ChangeAmount'], $row['ItemsTotal']);
        }
        unset($row);
        return [
            'items' => (new UserPhotoService($this->db))->attach($customerId, $rows, 'UserId'),
            'pagination' => ['page' => $page, 'pageSize' => $pageSize, 'total' => $total,
                'totalPages' => (int) ceil($total / $pageSize)],
        ];
    }
}
