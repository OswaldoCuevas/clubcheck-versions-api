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

    /** Valida el orden del historial contra opciones publicas para impedir columnas o direcciones SQL arbitrarias. */
    private function sortOption(array $query, string $key, string $default, array $allowed): string
    {
        $value = $query[$key] ?? $default;
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new ApiException("{$key} debe ser uno de: " . implode(', ', $allowed), 422);
        }
        return $value;
    }

    /** Consulta el historial del gimnasio, filtrando y ordenando antes de paginar sin excluir cancelaciones/bajas. */
    public function listing(string $customerId, array $query): array
    {
        $page = $this->pageNumber($query, 'page', 1, 1000000);
        $pageSize = $this->pageNumber($query, 'pageSize', 25, 100);
        $columns = ['memberName' => 'u.Fullname', 'planName' => "NULLIF(TRIM(i.ProductName), '')", 'paymentDate' => 't.SaleDate'];
        $sortBy = $this->sortOption($query, 'sortBy', 'paymentDate', array_keys($columns));
        $sortDir = $this->sortOption($query, 'sortDir', 'desc', ['asc', 'desc']);
        $search = $query['search'] ?? '';
        if (!is_string($search)) {
            throw new ApiException('search debe ser texto', 422);
        }
        $search = trim($search);
        // Las mismas relaciones y condiciones garantizan totales del conjunto filtrado, aun con relaciones ausentes.
        $from = 'FROM SaleTicketItemDesktop i
             LEFT JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId AND t.CustomerApiId = i.CustomerApiId
             LEFT JOIN SubscriptionsDesktop s ON s.Id = i.SubscriptionId AND s.CustomerApiId = i.CustomerApiId
             LEFT JOIN UsersDesktop u ON u.Id = s.UserId AND u.CustomerApiId = i.CustomerApiId';
        $where = "i.CustomerApiId = ? AND NULLIF(TRIM(i.SubscriptionId), '') IS NOT NULL";
        $params = [$customerId];
        if ($search !== '') {
            // La coincidencia parcial es literal: %, _ y el escape no se interpretan como comodines del usuario.
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search) . '%';
            $where .= " AND (u.Fullname LIKE ? ESCAPE '!' OR NULLIF(TRIM(i.ProductName), '') LIKE ? ESCAPE '!' OR t.Folio LIKE ? ESCAPE '!')";
            array_push($params, $pattern, $pattern, $pattern);
        }
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) AS total {$from} WHERE {$where}", $params)['total'];
        $order = $columns[$sortBy] . ' ' . strtoupper($sortDir) . ', i.Id DESC';
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
             {$from}
             LEFT JOIN AdministratorsDesktop a ON a.CustomerApiId = i.CustomerApiId AND a.Id = (
                SELECT seller.Id FROM AdministratorsDesktop seller WHERE seller.CustomerApiId = i.CustomerApiId
                    AND (seller.Id = t.CreatedBy OR seller.Username = t.CreatedBy)
                ORDER BY (seller.Id = t.CreatedBy) DESC, seller.Id DESC LIMIT 1)
             WHERE {$where}
             ORDER BY {$order} LIMIT ? OFFSET ?",
            array_merge($params, [$pageSize, ($page - 1) * $pageSize])
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
