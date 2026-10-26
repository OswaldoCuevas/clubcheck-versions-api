<?php
require_once __DIR__ . '/../app/Services/MemberDashboardService.php';

/** Prepara las tablas sincronizadas con columnas del esquema real en la base efímera de regresión members. */
function createDashboardTables(Database $db): void
{
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    foreach (['SubscriptionPeriodDesktop', 'SubscriptionsDesktop', 'AttendancesDesktop', 'SaleTicketDesktop', 'SaleTicketItemDesktop'] as $table) {
        preg_match('/CREATE TABLE `' . $table . '` \(.*?ENGINE=InnoDB[^;]*;/s', $schema, $match);
        // El fixture global tiene tablas padre mínimas y otra collation; las columnas se mantienen exactas.
        $sql = preg_replace('/,?\s*CONSTRAINT[^\n]+/', '', $match[0]);
        $db->query($sql);
    }
    $sql = file_get_contents(__DIR__ . '/../database/migrations/018_create_member_monthly_goals.sql');
    $db->query($sql);
    $db->query($sql); // Migración aditiva repetible.
}

/** Carga ventas mixtas, asistencias y membresías con fecha fija y compara el dominio sobre MySQL real. */
function checkDashboardResources(Database $db, App\Services\MembersService $members, array $identity, string $globalToken, string $otherToken): void
{
    $link = $members->requireContext($identity);
    $clock = static fn(DateTimeZone $zone) => new DateTimeImmutable('2024-03-20 12:00:00', $zone);
    $service = new App\Services\MemberDashboardService($db, $members, $clock);
    $db->insert('SubscriptionPeriodDesktop', ['Id' => 'period', 'CustomerApiId' => $link['CustomerApiId'], 'Name' => 'Mensual', 'CreatedOn' => '2024-01-01 00:00:00']);
    $db->insert('SubscriptionPeriodDesktop', ['Id' => 'period-z', 'CustomerApiId' => $link['CustomerApiId'], 'Name' => 'Desempate Z', 'CreatedOn' => '2024-01-01 00:00:00']);
    foreach (['old' => ['2024-01-01', '2024-12-31', 0], 'current-a' => ['2024-03-01', '2024-04-01', 0],
        'current-z' => ['2024-03-01', '2024-04-01', 0], 'future' => ['2024-04-01', '2025-01-01', 0],
        'removed' => ['2024-03-19', '2025-01-01', 1], 'cancelled' => ['2024-03-19', '2025-01-01', 0]] as $id => $fields) {
        $db->insert('SubscriptionsDesktop', ['Id' => $id, 'CustomerApiId' => $link['CustomerApiId'], 'UserId' => $link['UserId'],
            'StartDate' => $fields[0], 'EndingDate' => $fields[1], 'Removed' => $fields[2], 'SubscriptionPeriodId' => $id === 'current-z' ? 'period-z' : 'period', 'Finished' => 1]);
    }
    foreach (['mixed' => ['current-z', 138, 200, 62, null], 'cancelled' => ['cancelled', 88, 88, 0, '2024-03-19 12:00:00'],
        'partial' => ['old', 88, 40, 0, null], 'deleted-ticket' => ['old', 88, 88, 0, null]] as $id => $fields) {
        $db->insert('SaleTicketDesktop', ['Id' => $id, 'CustomerApiId' => $link['CustomerApiId'], 'Folio' => $id,
            'SaleDate' => '2024-03-01 10:00:00', 'SubtotalAmount' => $fields[1], 'TotalAmount' => $fields[1],
            'PaidAmount' => $fields[2], 'ChangeAmount' => $fields[3], 'PaymentMethod' => 'Efectivo', 'CancelledOn' => $fields[4], 'IsDeleted' => $id === 'deleted-ticket' ? 1 : 0]);
        $db->insert('SaleTicketItemDesktop', ['Id' => $id, 'CustomerApiId' => $link['CustomerApiId'], 'SaleTicketId' => $id,
            'SubscriptionId' => $fields[0], 'ProductName' => 'Plan histórico', 'UnitPrice' => 88, 'Quantity' => 1, 'LineSubtotal' => 88, 'LineTotal' => 88]);
    }
    $db->insert('SaleTicketItemDesktop', ['Id' => 'product', 'CustomerApiId' => $link['CustomerApiId'], 'SaleTicketId' => 'mixed', 'UnitPrice' => 50, 'Quantity' => 1, 'LineSubtotal' => 50, 'LineTotal' => 50]);
    for ($day = 1; $day <= 10; $day++) {
        foreach ([10, 11] as $hour) {
            $db->insert('AttendancesDesktop', ['Id' => App\Services\MembersService::uuid(), 'CustomerApiId' => $link['CustomerApiId'], 'UserId' => $link['UserId'],
                'CheckIn' => sprintf('2024-03-%02d %02d:00:00', $day, $hour), 'Active' => 1]);
        }
    }
    foreach ([['2024-03-01 10:00:00', 1, 0], ['2024-03-11 10:00:00', 0, 0], ['invalid', 1, 1], ['2024-03-20 13:00:00', 1, 0]] as $row) {
        $db->insert('AttendancesDesktop', ['Id' => App\Services\MembersService::uuid(), 'CustomerApiId' => $link['CustomerApiId'], 'UserId' => $link['UserId'], 'CheckIn' => $row[0], 'Active' => $row[1], 'Removed' => $row[2]]);
    }
    // Otro socio del mismo gimnasio no puede aportar visitas ni pagos al contexto seleccionado.
    $db->insert('UsersDesktop', ['Id' => 'foreign-member', 'CustomerApiId' => $link['CustomerApiId'], 'Fullname' => 'Privado']);
    $db->insert('AttendancesDesktop', ['Id' => 'foreign-visit', 'CustomerApiId' => $link['CustomerApiId'], 'UserId' => 'foreign-member', 'CheckIn' => '2024-03-11 10:00:00', 'Active' => 1]);
    $db->insert('SubscriptionsDesktop', ['Id' => 'foreign-subscription', 'CustomerApiId' => $link['CustomerApiId'], 'UserId' => 'foreign-member', 'StartDate' => '2024-03-19', 'EndingDate' => '2025-01-01']);
    $db->insert('SaleTicketItemDesktop', ['Id' => 'foreign-item', 'CustomerApiId' => $link['CustomerApiId'], 'SaleTicketId' => 'mixed', 'SubscriptionId' => 'foreign-subscription', 'UnitPrice' => 0, 'Quantity' => 1, 'LineSubtotal' => 0, 'LineTotal' => 0]);
    $dashboard = $service->dashboard($identity, []);
    check($dashboard['Member']['FirstName'] === 'Socio' && $dashboard['Membership'] === ['PeriodName' => 'Desempate Z', 'StartsOn' => '2024-03-01', 'ExpiresOn' => '2024-04-01'], 'Real identity / deterministic membership, future/removed/cancelled excluded');
    check($dashboard['MonthlyGoal'] === ['TargetDays' => 20, 'AttendedDays' => 10, 'ProgressPercent' => 50.0] && $dashboard['Attendance']['CurrentMonthVisits'] === 20, '10 unique days / 20 visits / 50 percent');
    $result = $service->monthlyGoal($identity, ['TargetDays' => 5]);
    check($result['ProgressPercent'] === 200.0, 'Real progress above goal');
    $db->update('MemberMonthlyGoals', ['UpdatedAt' => '2001-01-01 00:00:00', 'UpdatedBy' => 'audit-fixture'], 'LinkId = ?', [$link['Id']]);
    $before = $db->fetchOne('SELECT * FROM MemberMonthlyGoals WHERE LinkId = ?', [$link['Id']]);
    check($service->monthlyGoal($identity, ['TargetDays' => 5]) === $result, 'Idempotent summary');
    check($db->fetchOne('SELECT * FROM MemberMonthlyGoals WHERE LinkId = ?', [$link['Id']]) === $before, 'Idempotent persistence/audit');
    check((new App\Services\MemberDashboardService($db, $members, $clock))->dashboard($identity, [])['MonthlyGoal'] === $result, 'Goal persists in new instance');
    $other = $members->authenticate($otherToken);
    check($service->dashboard($other, [])['MonthlyGoal']['TargetDays'] === 20 && $service->payments($other, [])['Total'] === 0, 'Other gym goal/payments isolation');
    foreach ([0, -1, 1.5, '20', true, null, 2147483648] as $value) { rejects(fn() => $service->monthlyGoal($identity, ['TargetDays' => $value]), 'INVALID_INPUT', 400); }
    foreach (['dashboard', 'payments'] as $method) {
        rejects(fn() => $service->$method($members->authenticate($globalToken), []), 'CONTEXT_REQUIRED', 400);
        rejects(fn() => $service->$method($identity, ['UserId' => 'other']), 'INVALID_INPUT', 400);
    }
    rejects(fn() => $service->monthlyGoal($identity, ['TargetDays' => 20, 'UserId' => 'other']), 'INVALID_INPUT', 400);
    $bad = $identity; $bad['Payload']['UserId'] = 'foreign';
    rejects(fn() => $service->dashboard($bad, []), 'SESSION_INVALID', 401);
    $bad = $identity; unset($bad['Payload']['CustomerApiId']);
    rejects(fn() => $service->dashboard($bad, []), 'CONTEXT_REQUIRED', 400);
    $bad = $other; $bad['Payload'] = $identity['Payload']; $bad['Account']['Id'] = 'foreign-account';
    rejects(fn() => $service->payments($bad, []), 'LINK_DISABLED', 403);
    $db->update('UsersDesktop', ['Removed' => 1], 'Id = ?', [$link['UserId']]);
    rejects(fn() => $service->dashboard($identity, []), 'LINK_DISABLED', 403);
    $db->update('UsersDesktop', ['Removed' => 0], 'Id = ?', [$link['UserId']]);
    $db->update('Customers', ['IsActive' => 0], 'Id = ?', [$link['CustomerApiId']]);
    rejects(fn() => $service->monthlyGoal($identity, ['TargetDays' => 8]), 'LINK_DISABLED', 403);
    $db->update('Customers', ['IsActive' => 1], 'Id = ?', [$link['CustomerApiId']]);
    $payments = $service->payments($identity, ['PageSize' => '1']);
    check($payments['Total'] === 2 && count($payments['Items']) === 1 && $payments['Items'][0]['Id'] === 'partial' && $payments['Items'][0]['Amount'] === null, 'Pagination / missing payment attribution');
    $payment = $service->payments($identity, ['Page' => 2, 'PageSize' => 1])['Items'][0];
    check($payment['Id'] === 'mixed' && $payment['Amount'] === 88.0 && $payment['Currency'] === null && $payment['Folio'] === 'mixed', 'Mixed ticket attributed amount / no invented currency');
    check($payment['PaidAt'] === '2024-03-01T10:00:00' && $payment['PaymentDetails']['Method'] === 'Efectivo', 'Historical local date and details');
    $db->update('SaleTicketDesktop', ['Folio' => '', 'PaymentMethod' => ' ', 'PaymentReference' => ' '], 'Id = ?', ['mixed']);
    $db->update('SaleTicketItemDesktop', ['ProductName' => ''], 'Id = ?', ['mixed']);
    $missing = $service->payments($identity, ['Page' => 2, 'PageSize' => 1])['Items'][0];
    check($missing['Folio'] === null && $missing['PeriodName'] === null && $missing['PaymentDetails'] === null, 'Historical missing fields are explicit null');
    $db->update('SaleTicketDesktop', ['Folio' => 'mixed', 'PaymentMethod' => 'Efectivo', 'TotalAmount' => 50, 'PaidAmount' => 50, 'ChangeAmount' => 0], 'Id = ?', ['mixed']);
    $db->update('SaleTicketItemDesktop', ['ProductName' => 'Plan histórico', 'LineTotal' => 0], 'Id = ?', ['mixed']);
    check($service->payments($identity, ['Page' => 2, 'PageSize' => 1])['Items'][0]['Amount'] === 0.0, 'Zero is a known amount');
    $db->update('SaleTicketDesktop', ['TotalAmount' => 138, 'PaidAmount' => 200, 'ChangeAmount' => 62], 'Id = ?', ['mixed']);
    $db->update('SaleTicketItemDesktop', ['LineTotal' => 88], 'Id = ?', ['mixed']);
    foreach (['0', '1.2', true, [], '99999999999999999'] as $page) { rejects(fn() => $service->payments($identity, ['Page' => $page]), 'INVALID_INPUT', 400); }
    $db->update('SubscriptionsDesktop', ['Removed' => 1], 'UserId = ? AND CustomerApiId = ?', [$link['UserId'], $link['CustomerApiId']]);
    check($service->dashboard($identity, [])['Membership'] === null, 'No membership has explicit null');
    $db->update('SubscriptionsDesktop', ['Removed' => 0], 'Id IN (?, ?, ?, ?, ?)', ['old', 'current-a', 'current-z', 'future', 'cancelled']);
    $db->insert('AttendancesDesktop', ['Id' => 'invalid-date', 'CustomerApiId' => $link['CustomerApiId'], 'UserId' => $link['UserId'], 'CheckIn' => '2024-02-30 10:00:00', 'Active' => 1]);
    rejects(fn() => $service->dashboard($identity, []), 'ATTENDANCE_DATE_INVALID', 503);
    $db->query("DELETE FROM AttendancesDesktop WHERE Id = 'invalid-date'");
    putenv('MEMBERS_ATTENDANCE_TIME_ZONES={}');
    rejects(fn() => $service->dashboard($identity, []), 'ATTENDANCE_TIME_ZONE_REQUIRED', 503);
    rejects(fn() => $service->monthlyGoal($identity, ['TargetDays' => 15]), 'ATTENDANCE_TIME_ZONE_REQUIRED', 503);
    check((int) $db->fetchOne('SELECT TargetDays FROM MemberMonthlyGoals WHERE LinkId = ?', [$link['Id']])['TargetDays'] === 5, 'Temporal failure does not mutate goal');
    putenv('MEMBERS_ATTENDANCE_TIME_ZONES={"gym-1":"America/Mexico_City","gym-2":"America/Mexico_City","gym-3":"America/Mexico_City"}');
    echo "PASS: dashboard/payment/goal MySQL fixtures, membership selection, attribution, isolation, validation and persistence.\n";
}
