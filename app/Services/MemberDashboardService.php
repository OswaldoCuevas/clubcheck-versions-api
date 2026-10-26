<?php
namespace App\Services;

require_once __DIR__ . '/MembersService.php';

use App\Exceptions\MemberException;
use DateTimeImmutable;
use DateTimeZone;

/** Lecturas del socio seleccionado y configuración recurrente; no escribe tablas del escritorio. */
class MemberDashboardService
{
    private $db;
    private MembersService $members;
    private $clock;

    /** Comparte persistencia/autorización e inyecta reloj solo para fixtures de los cortes de negocio. */
    public function __construct($db, MembersService $members, ?callable $clock = null)
    {
        $this->db = $db;
        $this->members = $members;
        $this->clock = $clock ?? static fn(DateTimeZone $zone) => new DateTimeImmutable('now', $zone);
    }

    /** Rechaza identidades y campos ajenos al contrato, en lugar de permitir contexto desde el cliente. */
    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw new MemberException('Campos no permitidos.', 400, [], 'INVALID_INPUT');
        }
    }

    /** Exige zona confirmada del origen CheckIn por gimnasio; la zona del servidor no prueba la del escritorio. */
    private function now(array $link): DateTimeImmutable
    {
        $zones = json_decode((string) getenv('MEMBERS_ATTENDANCE_TIME_ZONES'), true);
        $name = is_array($zones) ? ($zones[$link['CustomerApiId']] ?? null) : null;
        if (!is_string($name) || !in_array($name, DateTimeZone::listIdentifiers(), true)) {
            throw new MemberException('La zona horaria de asistencia requiere confirmación del gimnasio.', 503, [], 'ATTENDANCE_TIME_ZONE_REQUIRED');
        }
        $zone = new DateTimeZone($name);
        return ($this->clock)($zone)->setTimezone($zone);
    }

    /** Lee fechas locales sincronizadas sin suponer UTC ni corregir calendarios inválidos. */
    public static function localDate(?string $value, DateTimeZone $zone): ?DateTimeImmutable
    {
        $value = trim(str_replace([',', ';'], ['', ':'], $value ?? ''));
        foreach (['Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value, $zone);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && (!$errors || (!$errors['warning_count'] && !$errors['error_count'])) && $date->format('Y') >= '1000') {
                return $date;
            }
        }
        return null;
    }

    /** Agrega entradas válidas; deduplica instante técnico y usa días únicos para calendario, meta y semanas. */
    public static function attendance(array $rows, DateTimeImmutable $now): array
    {
        $counts = []; $seen = []; $weeks = [];
        foreach ($rows as $row) {
            if ((int) $row['Active'] !== 1 || (int) ($row['Removed'] ?? 0) !== 0) { continue; }
            $date = self::localDate($row['CheckIn'], $now->getTimezone());
            if (!$date) {
                throw new MemberException('Hay fechas de asistencia que requieren revisión.', 503, [], 'ATTENDANCE_DATE_INVALID');
            }
            if ($date > $now) { continue; }
            $instant = $date->format('Y-m-d H:i:s.u');
            if (isset($seen[$instant])) { continue; }
            $seen[$instant] = true;
            $day = $date->format('Y-m-d');
            $counts[$day] = ($counts[$day] ?? 0) + 1;
            $weeks[$date->modify('monday this week')->format('Y-m-d')] = true;
        }
        $current = $now->format('Y-m');
        $previous = $now->modify('first day of this month')->modify('-1 month')->format('Y-m');
        $visits = 0; $old = 0; $attended = 0; $annual = 0;
        foreach ($counts as $day => $count) {
            if (substr($day, 0, 7) === $current) { $visits += $count; $attended++; }
            if (substr($day, 0, 7) === $previous) { $old += $count; }
            if (substr($day, 0, 4) === $now->format('Y')) { $annual++; }
        }
        $week = $now->modify('monday this week')->setTime(0, 0);
        if (!isset($weeks[$week->format('Y-m-d')])) { $week = $week->modify('-1 week'); }
        $streak = 0;
        while (isset($weeks[$week->format('Y-m-d')])) { $streak++; $week = $week->modify('-1 week'); }
        $days = [];
        $day = $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0);
        while ($day->format('Y') === $now->format('Y')) {
            $key = $day->format('Y-m-d');
            $days[] = ['Date' => $key, 'VisitCount' => $counts[$key] ?? 0];
            $day = $day->modify('+1 day');
        }
        return ['Attendance' => ['CurrentMonthVisits' => $visits, 'PreviousMonthVisits' => $old,
            'VisitsDifference' => $visits - $old, 'ActiveWeeks' => count($weeks), 'CurrentStreakWeeks' => $streak,
            'Year' => (int) $now->format('Y'), 'YearAttendanceDays' => $annual, 'Days' => $days], 'AttendedDays' => $attended];
    }

    /** Consulta únicamente accesos del socio/gimnasio autorizado para el progreso y la racha histórica. */
    private function activity(array $link, DateTimeImmutable $now): array
    {
        $rows = $this->db->fetchAll('SELECT CheckIn, Active, Removed FROM AttendancesDesktop WHERE UserId = ? AND CustomerApiId = ? AND Active = 1 AND COALESCE(Removed, 0) = 0', [$link['UserId'], $link['CustomerApiId']]);
        return self::attendance($rows, $now);
    }

    /** Calcula porcentaje real con dos decimales; el consumidor limita únicamente el indicador a 100. */
    private function goal(int $target, int $attended): array
    {
        return ['TargetDays' => $target, 'AttendedDays' => $attended, 'ProgressPercent' => round($attended * 100 / $target, 2)];
    }

    /** Devuelve saludo, membresía vigente determinista y actividad real del contexto revalidado. */
    public function dashboard(array $identity, array $query): array
    {
        $link = $this->members->requireContext($identity);
        $this->fields($query, []);
        $now = $this->now($link);
        $user = $this->db->fetchOne('SELECT Fullname FROM UsersDesktop WHERE Id = ? AND CustomerApiId = ?', [$link['UserId'], $link['CustomerApiId']]);
        $rows = $this->db->fetchAll('SELECT s.Id, s.StartDate, s.EndingDate, p.Name AS PeriodName FROM SubscriptionsDesktop s LEFT JOIN SubscriptionPeriodDesktop p ON p.Id = s.SubscriptionPeriodId AND p.CustomerApiId = s.CustomerApiId AND p.IsDeleted = 0 WHERE s.UserId = ? AND s.CustomerApiId = ? AND COALESCE(s.Removed, 0) = 0 AND NOT EXISTS (SELECT 1 FROM SaleTicketItemDesktop i JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId AND t.CustomerApiId = i.CustomerApiId WHERE i.SubscriptionId = s.Id AND i.CustomerApiId = s.CustomerApiId AND (t.Active = 0 OR t.CancelledOn IS NOT NULL OR t.IsDeleted = 1))', [$link['UserId'], $link['CustomerApiId']]);
        $valid = [];
        foreach ($rows as $row) {
            $start = self::localDate($row['StartDate'], $now->getTimezone());
            $end = self::localDate($row['EndingDate'], $now->getTimezone());
            if (!$start || !$end) {
                throw new MemberException('Hay fechas de membresía que requieren revisión.', 503, [], 'MEMBERSHIP_DATE_INVALID');
            }
            if ($start->format('Y-m-d') <= $now->format('Y-m-d') && $end->format('Y-m-d') >= $now->format('Y-m-d')) {
                $valid[] = ['Id' => $row['Id'], 'PeriodName' => $row['PeriodName'], 'StartsOn' => $start->format('Y-m-d'), 'ExpiresOn' => $end->format('Y-m-d')];
            }
        }
        usort($valid, static fn($a, $b) => [$b['StartsOn'], $b['ExpiresOn'], $b['Id']] <=> [$a['StartsOn'], $a['ExpiresOn'], $a['Id']]);
        $membership = $valid[0] ?? null;
        if ($membership) { unset($membership['Id']); }
        $activity = $this->activity($link, $now);
        $goal = $this->db->fetchOne('SELECT TargetDays FROM MemberMonthlyGoals WHERE LinkId = ? AND Active = 1 AND IsDeleted = 0', [$link['Id']]);
        $names = preg_split('/\s+/u', trim($user['Fullname'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        return ['LinkId' => $link['Id'], 'ReferenceDate' => $now->format('Y-m-d'), 'TimeZone' => $now->getTimezone()->getName(),
            'Member' => ['FirstName' => $names[0] ?? ''], 'Membership' => $membership,
            'BillingMode' => 'Manual', 'PaymentMethod' => 'Unregistered', 'Attendance' => $activity['Attendance'],
            'MonthlyGoal' => $this->goal((int) ($goal['TargetDays'] ?? 20), $activity['AttendedDays'])];
    }

    /** Guarda meta recurrente de forma idempotente sin aceptar identificadores ni confirmar cálculos fallidos. */
    public function monthlyGoal(array $identity, array $data, array $query = []): array
    {
        $link = $this->members->requireContext($identity);
        $this->fields($query, []);
        $this->fields($data, ['TargetDays']);
        $target = $data['TargetDays'] ?? null;
        if (!is_int($target) || $target < 1 || $target > 2147483647) {
            throw new MemberException('TargetDays debe ser un entero positivo de hasta 2147483647.', 400, [], 'INVALID_INPUT');
        }
        $activity = $this->activity($link, $this->now($link));
        $actor = $identity['Account']['Id']; $stamp = gmdate('Y-m-d H:i:s');
        $this->db->execute_query('INSERT INTO MemberMonthlyGoals (Id, LinkId, TargetDays, Active, IsDeleted, CreatedAt, UpdatedAt, CreatedBy, UpdatedBy) VALUES (?, ?, ?, 1, 0, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE UpdatedAt = IF(TargetDays <> VALUES(TargetDays) OR Active <> 1 OR IsDeleted <> 0, VALUES(UpdatedAt), UpdatedAt), UpdatedBy = IF(TargetDays <> VALUES(TargetDays) OR Active <> 1 OR IsDeleted <> 0, VALUES(UpdatedBy), UpdatedBy), TargetDays = VALUES(TargetDays), Active = 1, IsDeleted = 0', [MembersService::uuid(), $link['Id'], $target, $stamp, $stamp, $actor, $actor]);
        return $this->goal($target, $activity['AttendedDays']);
    }

    /** Valida paginación decimal estricta del historial y evita conversiones ambiguas del cliente. */
    private function page(array $query, string $field, int $default, int $maximum): int
    {
        $value = $query[$field] ?? $default;
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value) || strlen((string) $value) > strlen((string) $maximum) || (int) $value > $maximum) {
            throw new MemberException('Paginación inválida.', 400, [], 'INVALID_INPUT');
        }
        return (int) $value;
    }

    /** Lista partidas acreditadas del socio; concilia importes como ventas existentes sin repartir tickets mixtos. */
    public function payments(array $identity, array $query): array
    {
        $link = $this->members->requireContext($identity);
        $this->fields($query, ['Page', 'PageSize']);
        $page = $this->page($query, 'Page', 1, 1000000);
        $size = $this->page($query, 'PageSize', 20, 100);
        $from = 'FROM SaleTicketItemDesktop i JOIN SubscriptionsDesktop s ON s.Id = i.SubscriptionId AND s.CustomerApiId = i.CustomerApiId JOIN SaleTicketDesktop t ON t.Id = i.SaleTicketId AND t.CustomerApiId = i.CustomerApiId';
        $where = 's.UserId = ? AND s.CustomerApiId = ? AND COALESCE(s.Removed, 0) = 0 AND i.IsDeleted = 0 AND t.IsDeleted = 0 AND t.Active = 1 AND t.CancelledOn IS NULL';
        $params = [$link['UserId'], $link['CustomerApiId']];
        $total = $this->db->fetchOne("SELECT COUNT(*) AS Total {$from} WHERE {$where}", $params);
        $rows = $this->db->fetchAll("SELECT i.Id, NULLIF(TRIM(i.ProductName), '') AS PeriodName, s.StartDate, s.EndingDate, t.SaleDate AS PaidAt, NULLIF(TRIM(t.Folio), '') AS Folio, NULLIF(TRIM(t.PaymentMethod), '') AS PaymentMethod, NULLIF(TRIM(t.PaymentReference), '') AS PaymentReference,
            CASE WHEN i.LineTotal IS NOT NULL AND t.TotalAmount IS NOT NULL AND t.PaidAmount IS NOT NULL AND t.ChangeAmount IS NOT NULL
            AND ABS((SELECT SUM(li.LineTotal) FROM SaleTicketItemDesktop li WHERE li.SaleTicketId = t.Id AND li.CustomerApiId = i.CustomerApiId AND li.IsDeleted = 0) - t.TotalAmount) < 0.005
            AND ABS(t.PaidAmount - t.ChangeAmount - t.TotalAmount) < 0.005 THEN i.LineTotal ELSE NULL END AS Amount
            {$from} WHERE {$where} ORDER BY t.SaleDate DESC, i.Id DESC LIMIT ? OFFSET ?", array_merge($params, [$size, ($page - 1) * $size]));
        $items = [];
        // Fechas históricas locales sin offset: no asignar zona ni inventar moneda ausente del esquema.
        foreach ($rows as $row) {
            $zone = new DateTimeZone('UTC');
            $start = self::localDate($row['StartDate'], $zone); $end = self::localDate($row['EndingDate'], $zone);
            $paid = self::localDate($row['PaidAt'], $zone);
            $details = ['Method' => ($row['PaymentMethod'] ?? '') !== '' ? $row['PaymentMethod'] : null,
                'Reference' => ($row['PaymentReference'] ?? '') !== '' ? $row['PaymentReference'] : null];
            $items[] = ['Id' => $row['Id'], 'PeriodName' => $row['PeriodName'], 'StartsOn' => $start?->format('Y-m-d'), 'ExpiresOn' => $end?->format('Y-m-d'),
                'PaidAt' => $paid?->format('Y-m-d\TH:i:s'), 'Amount' => $row['Amount'] === null ? null : (float) $row['Amount'],
                'Currency' => null, 'Folio' => $row['Folio'], 'PaymentDetails' => $details['Method'] === null && $details['Reference'] === null ? null : $details];
        }
        return ['Items' => $items, 'Page' => $page, 'PageSize' => $size, 'Total' => (int) $total['Total']];
    }
}
