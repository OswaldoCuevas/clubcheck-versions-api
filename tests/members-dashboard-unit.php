<?php
require_once __DIR__ . '/../app/Services/MemberDashboardService.php';

use App\Services\MemberDashboardService as Dashboard;

/** Contrasta los cálculos de negocio con valores independientes y fecha fija, sin MySQL ni red. */
function assertDashboard(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

/** Construye entradas sintéticas válidas para probar visitas múltiples y días únicos. */
function entry(string $date, int $active = 1, int $removed = 0): array
{
    return ['CheckIn' => $date, 'Active' => $active, 'Removed' => $removed];
}

$zone = new DateTimeZone('America/Mexico_City');
$now = new DateTimeImmutable('2024-03-04 12:00:00', $zone); // Lunes vacío.
$rows = [entry('2024-02-12 10:00:00'), entry('2024-02-19 10:00:00'), entry('2024-02-29 10:00:00'),
    entry('2024-02-29 11:00:00'), entry('2024-02-29 11:00:00'), entry('2024-03-04 13:00:00'),
    entry('2024-03-01 10:00:00', 0), entry('2024-03-02 10:00:00', 1, 1)];
$result = Dashboard::attendance($rows, $now)['Attendance'];
assertDashboard($result['CurrentMonthVisits'] === 0 && $result['PreviousMonthVisits'] === 4 && $result['VisitsDifference'] === -4, 'Month change / visits / technical duplicate');
assertDashboard($result['CurrentStreakWeeks'] === 3 && $result['ActiveWeeks'] === 3, 'Current empty week keeps three');
assertDashboard($result['YearAttendanceDays'] === 3 && count($result['Days']) === 366, 'Leap year, unique days');
assertDashboard(array_column($result['Days'], 'VisitCount', 'Date')['2024-02-29'] === 2, 'Two real entries, one calendar day');
$rows[] = entry('2024-03-04 11:00:00');
assertDashboard(Dashboard::attendance($rows, $now)['Attendance']['CurrentStreakWeeks'] === 4, 'Attendance this week extends to four');
assertDashboard(Dashboard::attendance($rows, $now->modify('+14 days'))['Attendance']['CurrentStreakWeeks'] === 0, 'Completed empty week breaks streak');
$year = Dashboard::attendance([entry('2023-12-18 10:00:00'), entry('2023-12-25 10:00:00'), entry('2024-01-01 10:00:00')], new DateTimeImmutable('2024-01-08 12:00:00', $zone));
assertDashboard($year['Attendance']['CurrentStreakWeeks'] === 3 && $year['Attendance']['ActiveWeeks'] === 3 && $year['Attendance']['YearAttendanceDays'] === 1, 'Historical weeks cross year');
assertDashboard($year['Attendance']['VisitsDifference'] === -1, 'January compares December');
$empty = Dashboard::attendance([], $now)['Attendance'];
assertDashboard($empty['VisitsDifference'] === 0 && $empty['ActiveWeeks'] === 0 && $empty['CurrentStreakWeeks'] === 0, 'Empty history');
assertDashboard(Dashboard::localDate('2024-02-30', $zone) === null && Dashboard::localDate('2024-02-29, 10;00;00', $zone)?->format('Y-m-d H:i:s') === '2024-02-29 10:00:00', 'Strict legacy date normalization');
try {
    Dashboard::attendance([entry('invalid')], $now);
    throw new RuntimeException('Invalid dates cannot become zero');
} catch (App\Exceptions\MemberException $e) {
    assertDashboard($e->getErrorCode() === 'ATTENDANCE_DATE_INVALID', 'Explicit data error');
}
echo "PASS: independent attendance fixtures: leap year, month/year boundary, denied/deleted/future, duplicate, weeks and streak.\n";
