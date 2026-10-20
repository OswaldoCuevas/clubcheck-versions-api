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

    /** Resuelve el periodo general local para ventas; sin parametros conserva el historial completo. */
    private function period(array $query): ?array
    {
        if (!array_key_exists('range', $query) && !array_key_exists('from', $query) && !array_key_exists('to', $query)) {
            return null;
        }
        $range = $query['range'] ?? 'custom';
        if (!is_string($range)) {
            throw new ApiException('range debe ser texto', 422);
        }
        $range = strtolower(trim($range));
        $dates = [];
        foreach (['from', 'to'] as $key) {
            if (array_key_exists($key, $query)) {
                $value = $query[$key];
                $date = is_string($value) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
                if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') {
                    throw new ApiException("{$key} debe ser una fecha valida YYYY-MM-DD", 422);
                }
                $dates[$key] = $date;
            }
        }
        if (isset($dates['from'], $dates['to']) && $dates['from'] > $dates['to']) {
            throw new ApiException('from no puede ser mayor que to', 422);
        }
        if ($range === 'custom') {
            if (count($dates) !== 2) {
                throw new ApiException('from y to son obligatorios para range=custom', 422);
            }
            return [$dates['from']->format('Y-m-d 00:00:00'), $dates['to']->format('Y-m-d 23:59:59.999999')];
        }
        $days = ['today' => 1, 'week' => 7, 'fifteen' => 15, '15' => 15, '15days' => 15,
            'month' => 30, '30' => 30, '30days' => 30];
        if (!isset($days[$range])) {
            throw new ApiException('range debe ser today, week, fifteen, month o custom', 422);
        }
        $today = new \DateTimeImmutable('today');
        return [$today->modify('-' . ($days[$range] - 1) . ' days')->format('Y-m-d H:i:s'),
            $today->format('Y-m-d 23:59:59.999999')];
    }

    /** Comparte conciliacion DECIMAL entre tarjetas e indicadores; nunca reparte totales ni abonos del ticket. */
    private function amountExpression(): string
    {
        return "CASE WHEN i.LineTotal IS NOT NULL AND t.TotalAmount IS NOT NULL
            AND t.PaidAmount IS NOT NULL AND t.ChangeAmount IS NOT NULL
            AND ABS((SELECT SUM(li.LineTotal) FROM SaleTicketItemDesktop li
                WHERE li.SaleTicketId = t.Id AND li.CustomerApiId = i.CustomerApiId) - t.TotalAmount) < 0.005
            AND ABS(t.PaidAmount - t.ChangeAmount - t.TotalAmount) < 0.005
            THEN i.LineTotal ELSE NULL END";
    }

    /** Consulta historial y agregados del gimnasio; periodo/busqueda afectan todo y orden/pagina solo las tarjetas. */
    public function listing(string $customerId, array $query): array
    {
        $period = $this->period($query);
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
        if ($period !== null) {
            // Incluye todas las fracciones del ultimo dia local, incluso en el limite DATETIME 9999-12-31.
            $where .= ' AND t.SaleDate >= ? AND t.SaleDate <= ?';
            array_push($params, ...$period);
        }
        $amount = $this->amountExpression();
        $confirmed = 't.Active = 1 AND t.CancelledOn IS NULL';
        $totals = $this->db->fetchOne("SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN {$confirmed} THEN 1 ELSE 0 END), 0) AS soldCount,
                COALESCE(SUM(CASE WHEN {$confirmed} THEN {$amount} ELSE 0 END), 0) AS knownIncomeTotal,
                COALESCE(SUM(CASE WHEN {$confirmed} AND ({$amount}) IS NULL THEN 1 ELSE 0 END), 0) AS unknownAmountCount
             {$from} WHERE {$where}", $params);
        $total = (int) $totals['total'];
        $unknown = (int) $totals['unknownAmountCount'];
        $summary = ['soldCount' => (int) $totals['soldCount'],
            'incomeTotal' => $unknown > 0 ? null : (float) $totals['knownIncomeTotal'],
            'knownIncomeTotal' => (float) $totals['knownIncomeTotal'], 'unknownAmountCount' => $unknown];
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
                {$amount} AS AmountPaid
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
            $row['AmountPaid'] = $row['AmountPaid'] === null ? null : (float) $row['AmountPaid'];
            $row['AmountSource'] = $row['AmountPaid'] === null ? null : 'LineTotal';
        }
        unset($row);
        // El identificador se conserva aun sin catalogo local; nunca se une un plan de otro gimnasio.
        $planId = "NULLIF(TRIM(s.SubscriptionPeriodId), '')";
        $byPlan = $this->db->fetchAll("SELECT {$planId} AS subscriptionPeriodId,
                CASE WHEN {$planId} IS NULL THEN 'Sin plan identificado'
                    ELSE COALESCE(NULLIF(TRIM(p.Name), ''), 'Plan no disponible') END AS planName,
                COUNT(*) AS soldCount
             {$from}
             LEFT JOIN SubscriptionPeriodDesktop p ON p.Id = s.SubscriptionPeriodId AND p.CustomerApiId = i.CustomerApiId
             WHERE {$where} AND {$confirmed}
             GROUP BY {$planId}, planName
             ORDER BY soldCount DESC, subscriptionPeriodId ASC", $params);
        foreach ($byPlan as &$plan) {
            $plan['soldCount'] = (int) $plan['soldCount'];
        }
        unset($plan);
        return [
            'summary' => $summary, 'byPlan' => $byPlan,
            'items' => (new UserPhotoService($this->db))->attach($customerId, $rows, 'UserId'),
            'pagination' => ['page' => $page, 'pageSize' => $pageSize, 'total' => $total,
                'totalPages' => (int) ceil($total / $pageSize)],
        ];
    }
}
