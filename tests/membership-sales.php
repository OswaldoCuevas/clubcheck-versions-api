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
    $db->insert('AdministratorsDesktop', ['Id' => 'sales-admin', 'CustomerApiId' => 'gym-a', 'Username' => 'Historical seller', 'Removed' => 1]);
    $db->insert('AdministratorsDesktop', ['Id' => 'sales-foreign', 'CustomerApiId' => 'gym-b', 'Username' => 'PRIVATE']);
    foreach (['sales-current' => 0, 'sales-removed' => 1] as $id => $removed) {
        $db->insert('SubscriptionsDesktop', ['Id' => $id, 'CustomerApiId' => 'gym-a', 'UserId' => 'user-a',
            'StartDate' => '2026-01-01, 10;00;00', 'EndingDate' => '2032-02-29, 00;00;00', 'Removed' => $removed, 'Payment' => 999]);
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
    $rows = array_column($result['items'], null, 'SaleTicketItemId');
    checkPhoto(array_keys($rows) === ['sales-5', 'sales-4', 'sales-3', 'sales-2', 'sales-1'], 'Stable descending order, repeated user and gym isolation');
    checkPhoto($rows['sales-1']['AmountPaid'] === 88 && $rows['sales-1']['AmountSource'] === 'LineTotal', 'Mixed ticket, line discount, tax and cash change');
    checkPhoto($rows['sales-2']['IsCancelled'] === true && $rows['sales-2']['AmountPaid'] === 0 && $rows['sales-2']['UserId'] === 'user-a', 'Cancelled/deleted sale and removed subscription retained with zero');
    checkPhoto($rows['sales-3']['AmountPaid'] === null && $rows['sales-4']['AmountPaid'] === null, 'Unallocated ticket discount and partial payment remain unknown');
    checkPhoto($rows['sales-1']['SellerName'] === 'Historical seller' && $rows['sales-5']['SellerName'] === null && $rows['sales-5']['SellerId'] === null, 'Historical seller by username and foreign relationship isolation');
    checkPhoto($rows['sales-1']['PaymentDate'] === '2026-01-02 10:30:00' && $rows['sales-1']['StartDate'] === '2026-01-01 10:00:00'
        && $rows['sales-1']['DaysRemaining'] === (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable('2032-02-29'))->format('%r%a'), 'Local dates and days remaining');
    checkPhoto($rows['sales-1']['PlanName'] === 'Plan historico' && $rows['sales-1']['PaymentReference'] === null && $rows['sales-1']['photo']['version'] === 3, 'Historical plan, optional reference and photo');
    $page = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&page=2&pageSize=2')['body'], true);
    checkPhoto(array_column($page['items'], 'SaleTicketItemId') === ['sales-3', 'sales-2'] && $page['pagination'] === ['page' => 2, 'pageSize' => 2, 'total' => 5, 'totalPages' => 3], 'Sales pagination');
    $empty = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&page=100')['body'], true);
    checkPhoto($empty['items'] === [] && $empty['pagination']['total'] === 5, 'Empty page retains count');
    foreach (['page=0', 'page=-1', 'page=1.5', 'page[]=1', 'page=9999999999999999999999', 'pageSize=101', 'pageSize=abc'] as $invalid) {
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
    $multiple = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['body'], true)['items'];
    checkPhoto(array_column(array_slice($multiple, 0, 2), 'SaleTicketItemId') === ['multi-b', 'multi-a']
        && array_column(array_slice($multiple, 0, 2), 'AmountPaid') === [50, 30], 'Multiple memberships in same ticket keep their own persisted discounted amounts');
    require_once __DIR__ . '/../app/Services/MembershipSalesService.php';
    $noSales = (new App\Services\MembershipSalesService($db))->listing('gym-with-no-sales', []);
    checkPhoto($noSales['items'] === [] && $noSales['pagination']['totalPages'] === 0, 'Gym without sales');
    echo "Membership sales CGI: JWT, gym/relationship/photo isolation, repeated sales, cancellation, discounts, mixed tickets, cash change, zero/null, dates, ordering and pagination passed.\n";
}
