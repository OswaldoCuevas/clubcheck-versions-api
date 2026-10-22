<?php

require_once __DIR__ . '/../app/Services/MembershipSalesService.php';
require_once __DIR__ . '/../app/Middleware/DesktopJwtMiddleware.php';
require_once __DIR__ . '/../app/Core/Router.php';

/** Comprueba normalizacion del contrato sin MySQL; no sustituye la suite SQL/CGI de integracion. */
function checkSalesUnit(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

class MembershipSalesFixtureDatabase
{
    public array $calls = [];
    public array $rows = [];

    /** Entrega resumen controlado para comprobar serializacion del ingreso incompleto y metadatos de pagina. */
    public function fetchOne(string $sql, array $params): array
    {
        $this->calls[] = [$sql, $params];
        return ['total' => 5, 'soldCount' => 3, 'knownIncomeTotal' => '88.00', 'unknownAmountCount' => 2];
    }

    /** Simula proyecciones SQL de tarjetas, grupos y fotos para probar serializacion y consultas por lote. */
    public function fetchAll(string $sql, array $params): array
    {
        $this->calls[] = [$sql, $params];
        if (str_contains($sql, 'FROM UserPhotosDesktop')) {
            return [['UserId' => 'member', 'Version' => '3', 'UpdatedAt' => '2026-01-01 00:00:00']];
        }
        if (str_contains($sql, 'GROUP BY')) {
            return [['subscriptionPeriodId' => null, 'planName' => 'Sin plan identificado', 'soldCount' => '3']];
        }
        return $this->rows;
    }
}

$db = new MembershipSalesFixtureDatabase();
$base = ['SaleTicketItemId' => 'item', 'SaleTicketId' => 'ticket', 'SubscriptionId' => 'subscription',
    'PlanName' => 'Plan historico', 'Folio' => 'F-1', 'PaymentDate' => '2026-01-02 10:30:00',
    'PaymentMethod' => 'Efectivo', 'PaymentReference' => null, 'SellerId' => null, 'SellerName' => null,
    'AmountPaid' => '88.00', 'IsCancelled' => '0', 'CancelledOn' => null, 'CancellationReason' => null,
    'UserId' => 'member', 'Fullname' => 'Socio', 'StartDate' => '2026-01-01, 10;00;00',
    'EndingDate' => '2032-02-29, 00;00;00', 'LineTotal' => '88.00', 'TotalAmount' => '138.00',
    'PaidAmount' => '200.00', 'ChangeAmount' => '62.00', 'ItemsTotal' => '138.00'];
$db->rows = [$base,
    array_replace($base, ['AmountPaid' => '0.00', 'IsCancelled' => '1', 'LineTotal' => '0.00', 'TotalAmount' => '0.00', 'PaidAmount' => '0.00', 'ChangeAmount' => '0.00', 'ItemsTotal' => '0.00']),
    array_replace($base, ['AmountPaid' => null, 'TotalAmount' => '120.00', 'PaidAmount' => '120.00', 'ChangeAmount' => '0.00', 'EndingDate' => '2026-02-30']),
    array_replace($base, ['AmountPaid' => null, 'PaidAmount' => '50.00', 'ChangeAmount' => '0.00', 'StartDate' => null, 'EndingDate' => null]),
    array_replace($base, ['AmountPaid' => null, 'UserId' => null, 'PaymentDate' => null, 'IsCancelled' => null, 'TotalAmount' => null]),
];
foreach ($db->rows as &$row) {
    foreach (['LineTotal', 'TotalAmount', 'ItemsTotal', 'PaidAmount', 'ChangeAmount'] as $field) { unset($row[$field]); }
}
unset($row);
$service = new App\Services\MembershipSalesService($db);
$result = $service->listing('gym-a', ['page' => '2', 'pageSize' => '2']);
$items = $result['items'];
checkSalesUnit($result['pagination'] === ['page' => 2, 'pageSize' => 2, 'total' => 5, 'totalPages' => 3], 'Pagination metadata');
checkSalesUnit($items[0]['AmountPaid'] === 88.0 && $items[0]['AmountSource'] === 'LineTotal', 'Line discounts, mixed ticket and cash change');
checkSalesUnit($items[1]['AmountPaid'] === 0.0 && $items[1]['IsCancelled'] === true, 'Cancelled zero remains valid');
checkSalesUnit($items[2]['AmountPaid'] === null && $items[2]['EndingDate'] === null && $items[2]['DaysRemaining'] === null, 'Unallocated discount and impossible calendar date');
checkSalesUnit($items[3]['AmountPaid'] === null && $items[3]['StartDate'] === null && $items[3]['DaysRemaining'] === null, 'Partial payment and absent dates');
checkSalesUnit($items[4]['AmountPaid'] === null && $items[4]['photo'] === null && $items[4]['IsCancelled'] === null, 'Unknown values remain null');
checkSalesUnit($items[0]['StartDate'] === '2026-01-01 10:00:00' && $items[0]['PaymentDate'] === $base['PaymentDate']
    && $items[0]['DaysRemaining'] === (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable('2032-02-29'))->format('%r%a'), 'Local dates and leap day');
checkSalesUnit($items[0]['photo']['version'] === 3 && count($db->calls) === 4, 'Summary, page, plans and one batch photo query');
checkSalesUnit($db->calls[0][1] === ['gym-a'] && $db->calls[1][1] === ['gym-a', 2, 2]
    && $db->calls[2][1] === ['gym-a'] && $db->calls[3][1] === ['gym-a', 'member'], 'Bound gym, limits and deduplicated photo identities');
foreach (['LineTotal', 'TotalAmount', 'ItemsTotal', 'PaidAmount', 'ChangeAmount'] as $privateField) {
    checkSalesUnit(!array_key_exists($privateField, $items[0]), 'Ticket calculation detail omitted: ' . $privateField);
}
foreach (['page' => [0, -1, '1.5', [], true, '1000001', '9999999999999999999999'], 'pageSize' => ['101', 'abc', '', null],
    'search' => [[], true, 123], 'sortBy' => ['', 'Fullname', 'paymentDate DESC; DROP TABLE UsersDesktop', [], 1],
    'sortDir' => ['', 'ASC', 'desc, i.Id', [], false]] as $key => $values) {
    foreach ($values as $value) {
        if ($value === null) { continue; } // Ausencia/null usa el valor predeterminado.
        try {
            $service->listing('gym-a', [$key => $value]);
            throw new RuntimeException('Expected invalid ' . $key);
        } catch (App\Exceptions\ApiException $e) {
            checkSalesUnit($e->getStatusCode() === 422, 'Parameter status');
        }
    }
}
$db->rows = [];
foreach (['memberName' => 'u.Fullname', 'planName' => "NULLIF(TRIM(p.Name), '')", 'paymentDate' => 't.SaleDate'] as $sortBy => $column) {
    foreach (['asc', 'desc'] as $direction) {
        $db->calls = [];
        $service->listing('gym-a', ['page' => '3', 'pageSize' => '2', 'search' => "  50%_! O'Brien  ", 'sortBy' => $sortBy, 'sortDir' => $direction]);
        [$count, $listing] = $db->calls;
        $bound = ['gym-a', "%50!%!_!! O'Brien%", "%50!%!_!! O'Brien%", "%50!%!_!! O'Brien%"];
        checkSalesUnit($count[1] === $bound && $listing[1] === array_merge($bound, [2, 4]), 'Literal search and gym bound before pagination');
        $countFrom = substr($count[0], strpos($count[0], 'FROM SaleTicketItemDesktop i'));
        $listingFrom = substr($listing[0], strpos($listing[0], 'FROM SaleTicketItemDesktop i'));
        checkSalesUnit(str_starts_with($listingFrom, substr($countFrom, 0, strpos($countFrom, ' WHERE '))), 'Count and listing share scoped relationships');
        $where = substr($count[0], strrpos($count[0], ' WHERE '));
        checkSalesUnit(str_contains($listing[0], $where) && substr_count($where, "LIKE ? ESCAPE '!'") === 3, 'Count and listing share all search conditions');
        checkSalesUnit(str_contains($listing[0], "NULLIF(TRIM(p.Name), '') AS PlanName")
            && str_contains($listing[0], 'LEFT JOIN SubscriptionPeriodDesktop p ON p.Id = s.SubscriptionPeriodId')
            && !str_contains($listing[0], "NULLIF(TRIM(i.ProductName), '') AS PlanName"), 'Plan name comes from the related subscription period');
        checkSalesUnit(str_contains($listing[0], 'ORDER BY ' . $column . ' ' . strtoupper($direction) . ', i.Id DESC LIMIT ? OFFSET ?'), 'Allowed ordering and deterministic tie break');
    }
}
$db->calls = [];
$service->listing('gym-a', ['search' => "  \t "]);
checkSalesUnit($db->calls[0][1] === ['gym-a'] && !str_contains($db->calls[0][0], 'LIKE')
    && str_contains($db->calls[1][0], 'ORDER BY t.SaleDate DESC, i.Id DESC'), 'Blank search preserves default query');
$empty = $service->listing('gym-a', []);
checkSalesUnit($empty['items'] === [] && $empty['pagination'] === ['page' => 1, 'pageSize' => 25, 'total' => 5, 'totalPages' => 1], 'Defaults and empty page');
$router = require __DIR__ . '/../routes/web.php';
$reflection = new ReflectionClass($router);
$routes = $reflection->getProperty('routes')->getValue($router);
$matches = array_values(array_filter($routes, fn(array $route): bool => $route['uri'] === '/api/desktop/membership-sales'));
checkSalesUnit(count($matches) === 1 && $matches[0]['method'] === 'GET'
    && $matches[0]['controller'] === 'DesktopApiController' && $matches[0]['action'] === 'membershipSales'
    && $matches[0]['middleware'] === ['desktop_jwt'], 'Route registration and desktop JWT protection');
$jwt = new App\Services\JwtService('temporary-membership-sales-unit-secret');
$middleware = new App\Middleware\DesktopJwtMiddleware($jwt);
unset($_SERVER['HTTP_X_DESKTOP_JWT'], $_SERVER['HTTP_X_ACCESS_TOKEN'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
foreach (['' => 'TOKEN_MISSING', 'Bearer invalid' => 'TOKEN_INVALID',
    'Bearer ' . $jwt->createToken(['customerId' => 'gym-a']) => 'TOKEN_TYPE_INVALID'] as $header => $code) {
    $_SERVER['HTTP_AUTHORIZATION'] = $header;
    try {
        $middleware->handle();
        throw new RuntimeException('Expected unauthorized request');
    } catch (App\Exceptions\UnauthorizedException $e) {
        checkSalesUnit($e->getStatusCode() === 401 && $e->getErrorCode() === $code, 'Real middleware rejects ' . $code);
    }
}
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $jwt->createToken(['customerId' => 'gym-a', 'name' => 'Temporary gym']);
checkSalesUnit($middleware->handle() && App\Middleware\DesktopJwtMiddleware::getCurrentCustomerId() === 'gym-a', 'Real middleware accepts signed gym identity');
echo "Membership sales unit: amounts, null/zero, dates, cancellation, parameters, search bindings/shared filters, six orders, pagination, batched photos, route and real JWT middleware passed (fixture DB; SQL/CGI not executed).\n";

checkSalesUnit($result['summary'] === ['soldCount' => 3, 'incomeTotal' => null, 'knownIncomeTotal' => 88.0, 'unknownAmountCount' => 2]
    && $result['byPlan'][0]['soldCount'] === 3, 'Aggregate serialization preserves unknown income and integer counts');
foreach (['today' => 1, 'week' => 7, 'fifteen' => 15, '15' => 15, '15days' => 15, 'month' => 30, '30' => 30, '30days' => 30] as $range => $days) {
    $db->calls = [];
    $service->listing('gym-a', ['range' => (string) $range]);
    $today = new DateTimeImmutable('today');
    $expected = ['gym-a', $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d H:i:s'), $today->format('Y-m-d 23:59:59.999999')];
    checkSalesUnit($db->calls[0][1] === $expected && $db->calls[2][1] === $expected, 'General rolling local range: ' . $range);
}
$db->calls = [];
$service->listing('gym-a', ['range' => 'custom', 'from' => '2024-02-29', 'to' => '2024-02-29']);
checkSalesUnit($db->calls[0][1] === ['gym-a', '2024-02-29 00:00:00', '2024-02-29 23:59:59.999999'], 'Leap day and inclusive microsecond boundary');
foreach ([['range' => []], ['range' => 'custom', 'from' => "2026-01-01\0", 'to' => '2026-01-02'], ['range' => 'year'], ['range' => ''], ['range' => 'custom'],
    ['from' => '2026-02-30', 'to' => '2026-03-01'], ['from' => 'tomorrow', 'to' => '2026-03-01'],
    ['from' => '2026-01-02', 'to' => '2026-01-01'], ['range' => 'month', 'from' => []],
    ['range' => 'custom', 'from' => '2026-01-01 10:00:00', 'to' => '2026-01-02']] as $invalid) {
    try {
        $service->listing('gym-a', $invalid);
        throw new RuntimeException('Expected invalid period');
    } catch (App\Exceptions\ApiException $e) {
        checkSalesUnit($e->getStatusCode() === 422, 'Invalid period status');
    }
}
echo "Membership sales period and aggregate serialization/bindings passed (fixture DB).\n";
