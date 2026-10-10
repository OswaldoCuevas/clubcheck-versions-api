<?php

namespace App\Services;

require_once __DIR__ . '/UserPhotoService.php';

use App\Exceptions\ApiException;

/** Lecturas de la ficha sincronizada del socio; nunca modifica datos ni estados sync. */
class DesktopUserProfileService
{
    private $db;

    /** Reutiliza persistencia del backend y permite validar la ficha con un esquema temporal. */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /** Entrega la ficha y su membresia por mayor vencimiento valido, sin depender del historial paginado. */
    public function user(string $customerId, string $userId): array
    {
        $row = $this->requireUser($customerId, $userId);
        $row = (new UserPhotoService($this->db))->attach($customerId, [$row])[0];
        $row['currentSubscription'] = $this->currentSubscription($customerId, $userId);
        return $row;
    }

    /** Aplica identidad Users.Id, aislamiento del gimnasio y borrado logico antes de cualquier lectura. */
    private function requireUser(string $customerId, string $userId): array
    {
        $row = $this->db->fetchOne(
            'SELECT Id, Fullname, PhoneNumber, PhoneNumberEmergency, Gender, BirthDate, Code, Email, CreatedOn
             FROM UsersDesktop WHERE Id = ? AND CustomerApiId = ? AND COALESCE(Removed, 0) = 0',
            [$userId, $customerId]
        );
        if (!$row) {
            throw new ApiException('Socio no accesible', 404);
        }
        return $row;
    }

    /** Comparte fechas locales normalizadas y periodo no eliminado del gimnasio sin ocultar membresias sin catalogo. */
    private function subscriptionQuery(): array
    {
        $start = "NULLIF(TRIM(REPLACE(REPLACE(s.StartDate, ',', ''), ';', ':')), '')";
        $end = "NULLIF(TRIM(REPLACE(REPLACE(s.EndingDate, ',', ''), ';', ':')), '')";
        $fields = "s.Id, s.UserId, {$start} AS StartDate, {$end} AS EndingDate,
            s.SubscriptionPeriodId, s.Payment, s.Warning, s.Finished, s.Registered,
            p.Name AS SubscriptionPeriodName, p.Days AS SubscriptionPeriodDays";
        $table = 'SubscriptionsDesktop s LEFT JOIN SubscriptionPeriodDesktop p
            ON p.Id = s.SubscriptionPeriodId AND p.CustomerApiId = s.CustomerApiId AND p.IsDeleted = 0';
        return [$table, $fields, $start, $end];
    }

    /** Preserva Payment del registro (incluido cero) y entrega tipos JSON iguales en resumen e historial. */
    private function normalizeSubscription(array $item): array
    {
        $item['Payment'] = $item['Payment'] === null ? null : (float) $item['Payment'];
        $item['SubscriptionPeriodDays'] = $item['SubscriptionPeriodDays'] === null ? null : (int) $item['SubscriptionPeriodDays'];
        foreach (['Warning', 'Finished', 'Registered'] as $flag) {
            $item[$flag] = $item[$flag] === null ? null : (bool) $item[$flag];
        }
        return $item;
    }

    /** Elige mayor fecha calendario EndingDate e Id; inicio futuro/ausente no descarta y fechas imposibles no compiten. */
    private function currentSubscription(string $customerId, string $userId): ?array
    {
        [$table, $fields, , $end] = $this->subscriptionQuery();
        $day = "LEFT({$end}, 10)";
        // Validar el dia contra el mes evita que STR_TO_DATE acepte fechas como 2026-02-30.
        $validDay = "CASE WHEN {$day} REGEXP '^[1-9][0-9]{3}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$'
            THEN CASE WHEN CAST(RIGHT({$day}, 2) AS UNSIGNED) <= DAY(LAST_DAY(CONCAT(LEFT({$day}, 7), '-01')))
                THEN {$day} END END";
        $row = $this->db->fetchOne("SELECT {$fields} FROM {$table}
            WHERE s.CustomerApiId = ? AND s.UserId = ? AND COALESCE(s.Removed, 0) = 0
            AND ({$validDay}) IS NOT NULL ORDER BY {$day} DESC, s.Id DESC LIMIT 1", [$customerId, $userId]);
        return $row ? $this->normalizeSubscription($row) : null;
    }

    /** Valida fechas calendario locales inclusivas; los historiales no tienen filtro por defecto. */
    public function range(array $query, bool $required = false): array
    {
        $range = [];
        foreach (['from', 'to'] as $key) {
            $value = $query[$key] ?? null;
            if ($value === null && !$required) {
                $range[] = null;
                continue;
            }
            $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') {
                throw new ApiException("{$key} debe ser una fecha YYYY-MM-DD", 422);
            }
            $range[] = $value;
        }
        if ($range[0] !== null && $range[1] !== null && $range[0] > $range[1]) {
            throw new ApiException('from no puede ser mayor que to', 422);
        }
        return $range;
    }

    /** Valida paginacion acotada de la ficha para evitar consultas sin limite. */
    private function pageNumber(array $query, string $key, int $default, int $max): int
    {
        $value = $query[$key] ?? $default;
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)
            || strlen((string) $value) > 7 || (int) $value > $max) {
            throw new ApiException("{$key} debe estar entre 1 y {$max}", 422);
        }
        return (int) $value;
    }

    /** Devuelve intentos o membresias con periodo del mismo gimnasio; filtros/paginacion no definen el resumen actual. */
    public function history(string $customerId, string $userId, string $kind, array $query): array
    {
        $this->requireUser($customerId, $userId);
        [$from, $to] = $this->range($query);
        $page = $this->pageNumber($query, 'page', 1, 1000000);
        $size = $this->pageNumber($query, 'pageSize', 25, 100);
        $alias = '';
        if ($kind === 'attendances') {
            $table = 'AttendancesDesktop';
            $fields = 'Id, UserId, CheckIn, Active';
            $date = "STR_TO_DATE(CheckIn, '%Y-%m-%d %H:%i:%s')";
        } elseif ($kind === 'subscriptions') {
            [$table, $fields, $start] = $this->subscriptionQuery();
            $alias = 's.';
            $date = "STR_TO_DATE(LEFT({$start}, 10), '%Y-%m-%d')";
        } else {
            throw new \InvalidArgumentException('Historial no soportado');
        }
        $where = "{$alias}CustomerApiId = ? AND {$alias}UserId = ? AND COALESCE({$alias}Removed, 0) = 0";
        $params = [$customerId, $userId];
        if ($from !== null) {
            $where .= " AND {$date} >= ?";
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $where .= " AND {$date} <= ?";
            $params[] = $to . ' 23:59:59';
        }
        $total = (int) $this->db->fetchOne("SELECT COUNT(*) AS total FROM {$table} WHERE {$where}", $params)['total'];
        $items = $this->db->fetchAll("SELECT {$fields} FROM {$table} WHERE {$where} ORDER BY {$date} DESC, {$alias}Id DESC LIMIT ? OFFSET ?",
            array_merge($params, [$size, ($page - 1) * $size]));
        foreach ($items as &$item) {
            if ($kind === 'attendances') {
                $item['Active'] = (int) $item['Active'];
            } else {
                $item = $this->normalizeSubscription($item);
            }
        }
        unset($item);
        return ['items' => $items, 'page' => $page, 'pageSize' => $size, 'total' => $total];
    }

    /** Conserva ultimo intento diario de reportes; separa todos los intentos y dias con algun acceso permitido. */
    public function statistics(string $customerId, string $userId, array $query): array
    {
        $this->requireUser($customerId, $userId);
        [$from, $to] = $this->range($query, true);
        $params = [$customerId, $userId, $from . ' 00:00:00', $to . ' 23:59:59'];
        $date = "STR_TO_DATE(a.CheckIn, '%Y-%m-%d %H:%i:%s')";
        $base = "a.CustomerApiId = ? AND a.UserId = ? AND COALESCE(a.Removed, 0) = 0 AND {$date} BETWEEN ? AND ?";
        $summary = $this->db->fetchOne("SELECT COUNT(*) AS totalAttempts,
            COALESCE(SUM(a.Active = 1), 0) AS allowedAttempts, COALESCE(SUM(a.Active = 0), 0) AS deniedAttempts,
            COUNT(DISTINCT CASE WHEN a.Active = 1 THEN DATE({$date}) END) AS daysWithAllowedAccess
            FROM AttendancesDesktop a WHERE {$base}", $params);
        $summary = array_map('intval', $summary);
        $latest = "NOT EXISTS (SELECT 1 FROM AttendancesDesktop ax
            WHERE ax.CustomerApiId = a.CustomerApiId AND ax.UserId = a.UserId AND COALESCE(ax.Removed, 0) = 0
            AND DATE(STR_TO_DATE(ax.CheckIn, '%Y-%m-%d %H:%i:%s')) = DATE({$date})
            AND (STR_TO_DATE(ax.CheckIn, '%Y-%m-%d %H:%i:%s') > {$date}
                OR (STR_TO_DATE(ax.CheckIn, '%Y-%m-%d %H:%i:%s') = {$date} AND ax.Id > a.Id)))";
        $rows = $this->db->fetchAll("SELECT DATE({$date}) AS date, HOUR({$date}) AS hour, DAYOFWEEK({$date}) AS weekday, a.Active
            FROM AttendancesDesktop a WHERE {$base} AND {$latest} ORDER BY {$date} ASC", $params);
        $daily = [];
        $weekdays = [];
        foreach (['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'] as $i => $label) {
            $weekdays[$i + 1] = ['weekday' => $i + 1, 'label' => $label, 'total' => 0];
        }
        $hours = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hours[$hour] = ['hour' => $hour, 'label' => sprintf('%02d:00', $hour), 'total' => 0];
        }
        $end = new \DateTimeImmutable($to);
        for ($day = new \DateTimeImmutable($from); $day <= $end; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $daily[$key] = ['date' => $key, 'total' => 0, 'allowed' => 0, 'denied' => 0];
        }
        foreach ($rows as $row) {
            $daily[$row['date']]['total']++;
            if ((int) $row['Active'] === 1) {
                $daily[$row['date']]['allowed']++;
                $weekdays[(int) $row['weekday']]['total']++;
                $hours[(int) $row['hour']]['total']++;
            } elseif ((int) $row['Active'] === 0) {
                $daily[$row['date']]['denied']++;
            }
        }
        return ['userId' => $userId, 'range' => ['from' => $params[2], 'to' => $params[3]],
            'summary' => $summary, 'countMode' => 'latest_attempt_per_user_per_day',
            'daily' => array_values($daily), 'byWeekday' => array_values($weekdays), 'byHour' => array_values($hours)];
    }
}
