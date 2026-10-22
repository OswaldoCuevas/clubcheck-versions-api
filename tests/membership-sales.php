<?php

/** Verifica el historial de ventas con SQL, middleware, controlador y fotos reales en el esquema efimero. */
function checkMembershipSales(PhotoTestDatabase $db, App\Services\UserPhotoService $photos, string $schema, string $directory, string $token, string $otherToken): void
{
    $initial = file_get_contents(__DIR__ . '/../database/schema.sql');
    foreach (['AdministratorsDesktop', 'ProductDesktop', 'CashRegisterDesktop', 'SaleTicketDesktop', 'SaleTicketItemDesktop'] as $table) {
        if (!$db->fetchOne('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])) {
            preg_match('/CREATE TABLE `' . $table . '` \(.*?ENGINE=InnoDB[^;]*;/s', $initial, $match);
            $db->query($match[0]);
        }
    }
    require_once __DIR__ . '/../app/Core/Router.php';
    $router = require __DIR__ . '/../routes/web.php';
    $reflection = new ReflectionClass($router);
    $routes = $reflection->getProperty('routes')->getValue($router);
    $route = array_values(array_filter($routes, fn(array $route): bool => $route['uri'] === '/api/desktop/membership-sales'))[0];
    checkPhoto($route['method'] === 'GET' && $route['action'] === 'membershipSales' && $route['middleware'] === ['desktop_jwt'], 'Sales route and middleware');
    $db->insert('AdministratorsDesktop', ['Id' => 'sales-admin', 'CustomerApiId' => 'gym-a', 'Username' => 'Historical seller', 'Password' => 'test-only', 'Removed' => 1]);
    $db->insert('AdministratorsDesktop', ['Id' => 'sales-foreign', 'CustomerApiId' => 'gym-b', 'Username' => 'PRIVATE', 'Password' => 'test-only']);
    foreach (['sales-current' => 0, 'sales-removed' => 1] as $id => $removed) {
        $db->insert('SubscriptionsDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'UserId' => 'user-a',
            'StartDate' => '2026-01-01, 10;00;00', 'EndingDate' => '2032-02-29, 00;00;00',
            'SubscriptionPeriodId' => $removed ? 'period-deleted' : 'period-a', 'Removed' => $removed, 'Payment' => 999]);
    }
    // Ticket mixto: membresia 100-20+8=88 y otro concepto 50; recibido 200 y cambio 62.
    $tickets = [
        'sales-1' => ['TotalAmount' => 138, 'PaidAmount' => 200, 'ChangeAmount' => 62],
        'sales-2' => ['TotalAmount' => 0, 'PaidAmount' => 0, 'ChangeAmount' => 0, 'Active' => 0, 'IsDeleted' => 1, 'CancelledOn' => '2026-01-02 12:00:00'],
        'sales-3' => ['TotalAmount' => 70, 'PaidAmount' => 70, 'ChangeAmount' => 0, 'DiscountAmount' => 18],
        'sales-4' => ['TotalAmount' => 88, 'PaidAmount' => 40, 'ChangeAmount' => 0],
        'sales-5' => ['TotalAmount' => 88, 'PaidAmount' => 88, 'ChangeAmount' => 0, 'CreatedBy' => 'sales-foreign'],
        'sales-b' => ['CustomerApiId' => 'gym-b', 'TotalAmount' => 88, 'PaidAmount' => 88, 'ChangeAmount' => 0],
    ];
    foreach ($tickets as $id => $fields) {
        $db->insert('SaleTicketDesktop', array_replace(['Id' => $id, 'CustomerApiId' => 'gym-a', 'Folio' => $id,
            'SaleDate' => '2026-01-02 10:30:00', 'SubtotalAmount' => 100, 'PaymentMethod' => 'Efectivo', 'CreatedBy' => 'Historical seller'], $fields));
        $db->insert('SaleTicketItemDesktop', ['Id' => $id, 'CustomerApiId' => $fields['CustomerApiId'] ?? 'gym-a', 'SaleTicketId' => $id,
            'SubscriptionId' => $id === 'sales-2' ? 'sales-removed' : ($id === 'sales-b' ? 'membership-user-b' : 'sales-current'),
            'ProductName' => 'Plan historico', 'UnitPrice' => 100, 'Quantity' => 1, 'LineSubtotal' => 100,
            'LineDiscount' => $id === 'sales-2' ? 100 : 20, 'LineTax' => $id === 'sales-2' ? 0 : 8,
            'LineTotal' => $id === 'sales-2' ? 0 : 88, 'IsDeleted' => $id === 'sales-2' ? 1 : 0]);
    }
    $db->insert('SaleTicketItemDesktop', ['Id' => 'other-concept', 'CustomerApiId' => 'gym-a', 'SaleTicketId' => 'sales-1',
        'ProductName' => 'Producto', 'UnitPrice' => 50, 'Quantity' => 1, 'LineSubtotal' => 50, 'LineTotal' => 50]);
    $query = 'report=membership-sales';
    checkPhoto(photoCgi($schema, $directory, 'GET', '', '', '', $query)['status'] === 401, 'Sales require JWT');
    checkPhoto(photoCgi($schema, $directory, 'GET', 'invalid', '', '', $query)['status'] === 401, 'Sales reject invalid JWT');
    $response = photoCgi($schema, $directory, 'GET', $token, '', '', $query);
    checkPhoto($response['status'] === 200, 'Sales endpoint succeeds: ' . $response['body']);
    $result = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    checkPhoto($result['summary'] === ['soldCount' => 4, 'incomeTotal' => null, 'knownIncomeTotal' => 176, 'unknownAmountCount' => 2], 'Mixed ticket summary excludes products, change and cancelled sale');
    $rows = array_column($result['items'], null, 'SaleTicketItemId');
    checkPhoto(array_keys($rows) === ['sales-5', 'sales-4', 'sales-3', 'sales-2', 'sales-1'], 'Stable descending order, repeated user and gym isolation');
    checkPhoto($rows['sales-1']['AmountPaid'] === 88 && $rows['sales-1']['AmountSource'] === 'LineTotal', 'Mixed ticket, line discount, tax and cash change');
    checkPhoto($rows['sales-2']['IsCancelled'] === true && $rows['sales-2']['AmountPaid'] === 0 && $rows['sales-2']['UserId'] === 'user-a', 'Cancelled/deleted sale and removed subscription retained with zero');
    checkPhoto($rows['sales-3']['AmountPaid'] === null && $rows['sales-4']['AmountPaid'] === null, 'Unallocated ticket discount and partial payment remain unknown');
    checkPhoto($rows['sales-1']['SellerName'] === 'Historical seller' && $rows['sales-5']['SellerName'] === null && $rows['sales-5']['SellerId'] === null, 'Historical seller by username and foreign relationship isolation');
    checkPhoto($rows['sales-1']['PaymentDate'] === '2026-01-02 10:30:00' && $rows['sales-1']['StartDate'] === '2026-01-01 10:00:00'
        && $rows['sales-1']['DaysRemaining'] === (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable('2032-02-29'))->format('%r%a'), 'Local dates and days remaining');
    checkPhoto($rows['sales-1']['PlanName'] === 'Mensual' && $rows['sales-2']['PlanName'] === 'Eliminado'
        && $rows['sales-1']['PaymentReference'] === null && $rows['sales-1']['photo']['version'] === 3,
        'Related period name, logically deleted period, optional reference and photo');
    $page = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&page=2&pageSize=2')['body'], true);
    checkPhoto(array_column($page['items'], 'SaleTicketItemId') === ['sales-3', 'sales-2'] && $page['pagination'] === ['page' => 2, 'pageSize' => 2, 'total' => 5, 'totalPages' => 3], 'Sales pagination');
    $empty = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&page=100')['body'], true);
    checkPhoto($empty['items'] === [] && $empty['pagination']['total'] === 5, 'Empty page retains count');
    foreach (['page=0', 'page=-1', 'page=1.5', 'page[]=1', 'page=9999999999999999999999', 'pageSize=101', 'pageSize=abc',
        'search[]=x', 'sortBy=Fullname', 'sortBy[]=planName', 'sortBy=paymentDate%3BDROP', 'sortDir=ASC', 'sortDir=', 'sortDir[]=desc'] as $invalid) {
        $rejected = photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&' . $invalid);
        checkPhoto($rejected['status'] === 422 && json_decode($rejected['body'], true)['error_code'] === 'API_ERROR', 'Invalid sales parameter ' . $invalid);
    }
    $other = json_decode(photoCgi($schema, $directory, 'GET', $otherToken, '', '', $query . '&customerApiId=gym-a')['body'], true);
    checkPhoto(array_column($other['items'], 'SaleTicketItemId') === ['sales-b'] && $other['items'][0]['photo'] === null, 'JWT gym cannot be overridden; missing photo');
    $download = photoCgi($schema, $directory, 'GET', $token, '', '', 'userId=' . $rows['sales-1']['UserId']);
    checkPhoto($download['status'] === 200 && $download['body'] === $photos->thumbnail('gym-a', 'user-a'), 'Sales to protected photo flow');
    checkPhoto(photoCgi($schema, $directory, 'GET', $otherToken, '', '', 'userId=user-a')['status'] === 404, 'Sales photo isolated');
    // Simula sincronizacion incompleta con claves existentes de otro gimnasio; no deben enriquecer la partida.
    $db->update('SaleTicketItemDesktop', ['SubscriptionId' => 'membership-user-b', 'ProductName' => null], 'Id = ?', ['sales-5']);
    $db->update('SubscriptionsDesktop', ['EndingDate' => '2026-02-30'], 'Id = ?', ['sales-current']);
    $incomplete = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['body'], true)['items'];
    checkPhoto($incomplete[0]['UserId'] === null && $incomplete[0]['Fullname'] === null && $incomplete[0]['photo'] === null && $incomplete[0]['PlanName'] === null, 'Incomplete/foreign subscription keeps sale without invented data');
    checkPhoto($incomplete[1]['EndingDate'] === null && $incomplete[1]['DaysRemaining'] === null, 'Impossible date remains unknown');
    $db->update('SaleTicketItemDesktop', ['SaleTicketId' => 'sales-b'], 'Id = ?', ['sales-5']);
    $missingTicket = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['body'], true)['items'];
    $orphan = array_column($missingTicket, null, 'SaleTicketItemId')['sales-5'];
    checkPhoto(count($missingTicket) === 5 && $orphan['Folio'] === null && $orphan['PaymentDate'] === null
        && $orphan['AmountPaid'] === null && $orphan['IsCancelled'] === null, 'Foreign/missing ticket does not hide historical item or expose foreign details');
    $db->insert('SaleTicketDesktop', ['Id' => 'sales-multiple', 'CustomerApiId' => 'gym-a', 'Folio' => 'MULTIPLE',
        'SaleDate' => '2026-01-03 10:00:00', 'SubtotalAmount' => 100, 'DiscountAmount' => 20,
        'TotalAmount' => 80, 'PaidAmount' => 80, 'PaymentMethod' => 'Tarjeta']);
    foreach (['multi-a' => 30, 'multi-b' => 50] as $id => $amount) {
        $db->insert('SaleTicketItemDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'SaleTicketId' => 'sales-multiple',
            'SubscriptionId' => $id === 'multi-a' ? 'sales-current' : 'sales-removed',
            'ProductName' => 'Plan vendido', 'UnitPrice' => 50, 'Quantity' => 1,
            'LineSubtotal' => 50, 'LineDiscount' => 50 - $amount, 'LineTotal' => $amount]);
    }
    $ticketSummary = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&search=MULTIPLE')['body'], true);
    checkPhoto($ticketSummary['summary'] === ['soldCount' => 2, 'incomeTotal' => 80, 'knownIncomeTotal' => 80, 'unknownAmountCount' => 0], 'Multiple memberships sum their amounts without repeating ticket total');
    $multiple = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['body'], true)['items'];
    checkPhoto(array_column(array_slice($multiple, 0, 2), 'SaleTicketItemId') === ['multi-b', 'multi-a']
        && array_column(array_slice($multiple, 0, 2), 'AmountPaid') === [50, 30], 'Multiple memberships in same ticket keep their own persisted discounted amounts');
    // Doce partidas anteriores a la primera pagina: cada fuente de busqueda debe consultar todo el historial.
    $expected = [];
    for ($number = 1; $number <= 12; $number++) {
        $id = sprintf('criteria-%02d', $number);
        $member = 'Socio global ' . ($number % 2 === 0 ? 'Ana' : 'Zoe');
        $plan = 'Plan historico 2020-01-01 ' . ($number % 3 === 0 ? 'Anual' : 'Mensual');
        $periodId = 'period-' . $id;
        $date = '2020-01-' . ($number % 2 === 0 ? '01' : '02') . ' 10:00:00';
        $db->insert('UsersDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'Fullname' => $member, 'PhoneNumber' => '000', 'BirthDate' => '2000-01-01']);
        $db->insert('SubscriptionPeriodDesktop', ['Id' => $periodId, 'CustomerApiId' => 'gym-a', 'Name' => $plan, 'CreatedOn' => '2020-01-01']);
        $db->insert('SubscriptionsDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'UserId' => $id,
            'StartDate' => '2020-01-01', 'EndingDate' => '2020-02-01', 'SubscriptionPeriodId' => $periodId]);
        $db->insert('SaleTicketDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'Folio' => 'GLOBAL-' . $id,
            'SaleDate' => $date, 'SubtotalAmount' => 0, 'TotalAmount' => 0, 'PaidAmount' => 0,
            'PaymentMethod' => 'Efectivo', 'Active' => $number === 1 ? 0 : 1]);
        $db->insert('SaleTicketItemDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'SaleTicketId' => $id,
            'SubscriptionId' => $id, 'ProductName' => $plan, 'UnitPrice' => 0, 'Quantity' => 1, 'LineSubtotal' => 0, 'LineTotal' => 0]);
        $expected[] = ['SaleTicketItemId' => $id, 'memberName' => $member, 'planName' => $plan, 'paymentDate' => $date];
    }
    foreach (['Socio global', 'historico 2020-01-01', 'GLOBAL-criteria'] as $search) {
        $found = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&' . http_build_query([
            'search' => $search, 'pageSize' => 10, 'page' => 2]))['body'], true);
        checkPhoto(count($found['items']) === 2 && $found['pagination'] === ['page' => 2, 'pageSize' => 10, 'total' => 12, 'totalPages' => 2], 'Global search beyond first page: ' . $search);
    }
    foreach (['memberName', 'planName', 'paymentDate'] as $sortBy) {
        foreach (['asc', 'desc'] as $sortDir) {
            $ordered = $expected;
            // Oraculo independiente: valor visible en la direccion solicitada e identificador descendente en empates.
            usort($ordered, function (array $left, array $right) use ($sortBy, $sortDir): int {
                $primary = strcmp($left[$sortBy], $right[$sortBy]);
                return ($sortDir === 'asc' ? $primary : -$primary) ?: strcmp($right['SaleTicketItemId'], $left['SaleTicketItemId']);
            });
            $actualIds = [];
            foreach ([1, 2, 3] as $pageNumber) {
                $sorted = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&' . http_build_query([
                    'search' => 'GLOBAL-criteria', 'sortBy' => $sortBy, 'sortDir' => $sortDir, 'pageSize' => 5, 'page' => $pageNumber]))['body'], true);
                checkPhoto($sorted['pagination']['total'] === 12 && $sorted['pagination']['totalPages'] === 3, 'Filtered count without duplicates');
                $actualIds = array_merge($actualIds, array_column($sorted['items'], 'SaleTicketItemId'));
            }
            checkPhoto($actualIds === array_column($ordered, 'SaleTicketItemId'), 'Global SQL order and stable ties: ' . $sortBy . ' ' . $sortDir);
        }
    }
    foreach (['no-such-plan', "' OR 1=1 --", '%', '_', '!', 'PRIVATE'] as $search) {
        $none = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&' . http_build_query(['search' => $search]))['body'], true);
        checkPhoto($none['items'] === [] && $none['pagination']['total'] === 0 && $none['pagination']['totalPages'] === 0, 'Literal search/no matches: ' . $search);
    }
    $db->update('SubscriptionPeriodDesktop', ['Name' => "Plan 50%_! O'Brien"], 'Id = ?', ['period-criteria-01']);
    $literal = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&' . http_build_query(['search' => "  50%_! O'Brien  "]))['body'], true);
    checkPhoto(array_column($literal['items'], 'SaleTicketItemId') === ['criteria-01'] && $literal['items'][0]['IsCancelled'] === true
        && $literal['items'][0]['AmountPaid'] === 0 && $literal['items'][0]['DaysRemaining'] < 0, 'Literal wildcards, apostrophe, trimmed search, cancelled zero and expired days');
    $foreign = json_decode(photoCgi($schema, $directory, 'GET', $otherToken, '', '', $query . '&search=GLOBAL-criteria&customerApiId=gym-a')['body'], true);
    checkPhoto($foreign['items'] === [] && $foreign['pagination']['total'] === 0, 'Search cannot escape JWT gym');
    $all = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&search=%20%20')['body'], true);
    checkPhoto($all['pagination']['total'] === 19, 'Whitespace search does not restrict results');
    foreach (['memberName' => 'Fullname', 'planName' => 'PlanName', 'paymentDate' => 'PaymentDate'] as $sortBy => $field) {
        $nullFirst = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&sortBy=' . $sortBy . '&sortDir=asc')['body'], true);
        checkPhoto($nullFirst['items'][0]['SaleTicketItemId'] === 'sales-5' && $nullFirst['items'][0][$field] === null, 'SQL null order retains incomplete sale: ' . $sortBy);
    }
    require_once __DIR__ . '/../app/Services/MembershipSalesService.php';
    $noSales = (new App\Services\MembershipSalesService($db))->listing('gym-with-no-sales', []);
    checkPhoto($noSales['items'] === [] && $noSales['pagination']['totalPages'] === 0, 'Gym without sales');
    checkMembershipSalesAggregates($db, $schema, $directory, $token, $otherToken);
    echo "Membership sales CGI: JWT, gym/relationship/photo isolation, cancellation, amounts, zero/null, dates, global/literal search, six orders with ties, filtered counts, validation and pagination passed.\n";
}

/** Prueba periodo/agregados con API CGI real y datos efimeros; comprueba conteo por partida y conciliacion decimal. */
function checkMembershipSalesAggregates(PhotoTestDatabase $db, string $schema, string $directory, string $token, string $otherToken): void
{
    foreach (['agg-plan' => ['gym-a', 'Mensual actual'], 'agg-foreign' => ['gym-b', 'PRIVATE PLAN']] as $id => [$gym, $name]) {
        $db->insert('SubscriptionPeriodDesktop', ['Id' => $id, 'CustomerApiId' => $gym, 'Name' => $name, 'CreatedOn' => '2026-01-01']);
    }
    foreach (['agg-sub' => 'agg-plan', 'agg-missing' => 'not-synced', 'agg-other' => 'agg-foreign', 'agg-none' => null] as $id => $planId) {
        $db->insert('SubscriptionsDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'UserId' => 'user-a', 'SubscriptionPeriodId' => $planId]);
    }
    // Incluye ambos extremos del dia, cantidad > 1, cero, desconocidos, cancelaciones y relaciones incompletas.
    $fixtures = [
        'agg-start' => ['2040-02-01 00:00:00', 'agg-sub', '0.10', 1, null, true],
        'agg-end' => ['2040-02-02 23:59:59', 'agg-sub', '0.20', 1, null, true],
        'agg-missing' => ['2040-02-02 12:00:00', 'agg-missing', '0.30', 1, null, true],
        'agg-foreign' => ['2040-02-02 12:00:00', 'agg-other', '0.40', 1, null, true],
        'agg-none' => ['2040-02-02 12:00:00', 'agg-none', '0.00', 1, null, true],
        'agg-unknown' => ['2040-02-02 12:00:00', 'agg-none', '1.00', 1, null, false],
        'agg-cancel' => ['2040-02-02 12:00:00', 'agg-sub', '100.00', 0, null, true],
        'agg-cancel-date' => ['2040-02-02 12:00:00', 'agg-sub', '100.00', 1, '2040-02-02 12:00:01', true],
        'agg-state' => ['2040-02-02 12:00:00', 'agg-sub', '100.00', null, null, true],
        'agg-state-other' => ['2040-02-02 12:00:00', 'agg-sub', '100.00', 2, null, true],
        'agg-before' => ['2040-01-31 23:59:59', 'agg-sub', '100.00', 1, null, true],
        'agg-after' => ['2040-02-03 00:00:00', 'agg-sub', '100.00', 1, null, true],
    ];
    foreach ($fixtures as $id => [$date, $subscription, $amount, $active, $cancelled, $paid]) {
        $db->insert('SaleTicketDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'Folio' => $id, 'SaleDate' => $date,
            'SubtotalAmount' => $amount, 'TotalAmount' => $amount, 'PaidAmount' => $paid ? $amount : '0.00',
            'PaymentMethod' => 'Efectivo', 'Active' => $active, 'CancelledOn' => $cancelled]);
        $db->insert('SaleTicketItemDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'SaleTicketId' => $id,
            'SubscriptionId' => $subscription, 'ProductName' => 'Historico AGGREGATES', 'Quantity' => 7,
            'UnitPrice' => $amount, 'LineSubtotal' => $amount, 'LineTotal' => $amount]);
    }
    $query = 'report=membership-sales&range=custom&from=2040-02-01&to=2040-02-02&search=agg-';
    $response = photoCgi($schema, $directory, 'GET', $token, '', '', $query);
    checkPhoto($response['status'] === 200, 'Period/aggregates HTTP success: ' . $response['body']);
    $result = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    $expected = ['soldCount' => 6, 'incomeTotal' => null, 'knownIncomeTotal' => 1, 'unknownAmountCount' => 1];
    checkPhoto($result['summary'] === $expected && $result['pagination']['total'] === 10, 'Decimal sum, unknown income, cancellation and Quantity ignored');
    $plans = array_column($result['byPlan'], null, 'subscriptionPeriodId');
    checkPhoto(count($plans) === 4 && $plans['agg-plan'] === ['subscriptionPeriodId' => 'agg-plan', 'planName' => 'Mensual actual', 'soldCount' => 2]
        && $plans['not-synced']['planName'] === 'Plan no disponible' && $plans['not-synced']['soldCount'] === 1
        && $plans['agg-foreign']['planName'] === 'Plan no disponible' && $plans['agg-foreign']['soldCount'] === 1
        && $plans['']['planName'] === 'Sin plan identificado' && $plans['']['soldCount'] === 2, 'Current catalog, missing ID/catalog and foreign catalog isolation');
    checkPhoto(array_sum(array_column($result['byPlan'], 'soldCount')) === $result['summary']['soldCount'], 'Plan counts reconcile');
    $rows = array_column($result['items'], null, 'SaleTicketItemId');
    checkPhoto(isset($rows['agg-start'], $rows['agg-end'], $rows['agg-cancel']) && !isset($rows['agg-before'], $rows['agg-after'])
        && $rows['agg-none']['AmountPaid'] === 0 && $rows['agg-unknown']['AmountPaid'] === null
        && $rows['agg-start']['PlanName'] === 'Mensual actual' && $rows['agg-missing']['PlanName'] === null
        && $rows['agg-state']['IsCancelled'] === null, 'Inclusive local boundaries, related period names and zero/null unchanged');
    foreach (['page=2&pageSize=2', 'page=99', 'sortBy=planName&sortDir=asc', 'sortBy=memberName&sortDir=desc'] as $variation) {
        $changed = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&' . $variation)['body'], true);
        checkPhoto($changed['summary'] === $expected && $changed['byPlan'] === $result['byPlan'], 'Global aggregates independent of ' . $variation);
    }
    $known = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&search=agg-start')['body'], true);
    checkPhoto($known['summary'] === ['soldCount' => 1, 'incomeTotal' => 0.1, 'knownIncomeTotal' => 0.1, 'unknownAmountCount' => 0]
        && count($known['byPlan']) === 1, 'Search applies before summary/grouping');
    $noSales = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&from=2041-01-01&to=2041-01-02')['body'], true);
    checkPhoto($noSales['summary'] === ['soldCount' => 0, 'incomeTotal' => 0, 'knownIncomeTotal' => 0, 'unknownAmountCount' => 0]
        && $noSales['byPlan'] === [] && $noSales['items'] === [], 'Empty filtered set returns zero');
    $foreign = json_decode(photoCgi($schema, $directory, 'GET', $otherToken, '', '', $query . '&customerApiId=gym-a')['body'], true);
    checkPhoto($foreign['summary']['soldCount'] === 0 && $foreign['byPlan'] === [], 'Aggregate JWT isolation');
    foreach (['range[]=custom', 'range=year', 'range=custom', 'range=custom&from=2040-02-30&to=2040-03-01',
        'range=custom&from=2040-02-02&to=2040-02-01', 'range=custom&from[]=2040-02-01&to=2040-02-02',
        'range=custom&from=tomorrow&to=2040-02-02', 'range=month&from=invalid'] as $invalid) {
        checkPhoto(photoCgi($schema, $directory, 'GET', $token, '', '', 'report=membership-sales&' . $invalid)['status'] === 422, 'Period validation: ' . $invalid);
    }
    // El contrato general usa ventanas moviles; no cambia month a mes calendario.
    foreach (['today' => 1, 'week' => 7, 'fifteen' => 15, 'month' => 30] as $range => $days) {
        $today = new DateTimeImmutable('today');
        $db->update('SaleTicketDesktop', ['SaleDate' => $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00')], 'Id = ?', ['agg-start']);
        $db->update('SaleTicketDesktop', ['SaleDate' => $today->modify('-' . $days . ' days')->format('Y-m-d 23:59:59')], 'Id = ?', ['agg-end']);
        $rolling = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'report=membership-sales&search=agg-&range=' . $range)['body'], true);
        checkPhoto(array_column($rolling['items'], 'SaleTicketItemId') === ['agg-start'] && $rolling['summary']['incomeTotal'] === 0.1, 'Rolling boundary in real API: ' . $range);
    }
    echo "Membership sales aggregates CGI: local/inclusive/rolling periods, 422, decimal totals, unknowns, confirmed state, Quantity, catalogs, JWT isolation and page/order independence passed.\n";
}
