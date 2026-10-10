<?php

/** Valida ficha, historiales, estadisticas y originales con JWT real y datos exclusivamente temporales. */
function checkUserProfile(PhotoTestDatabase $db, App\Services\UserPhotoService $photos, string $schema, string $directory, string $token, string $otherToken): void
{
    require_once __DIR__ . '/../app/Core/Router.php';
    $router = require __DIR__ . '/../routes/web.php';
    $reflection = new ReflectionClass($router);
    $routes = $reflection->getProperty('routes')->getValue($router);
    $matchRoute = $reflection->getMethod('matchRoute');
    foreach (['' => ['DesktopUserProfileController', 'user'],
        '/attendances' => ['DesktopUserProfileController', 'attendances'],
        '/subscriptions' => ['DesktopUserProfileController', 'subscriptions'],
        '/statistics' => ['DesktopUserProfileController', 'statistics'],
        '/photo/original' => ['UserPhotosController', 'readOriginal'],
        '/photo' => ['UserPhotosController', 'read']] as $suffix => $expected) {
        $selected = null;
        foreach ($routes as $route) {
            if ($matchRoute->invoke($router, $route, 'GET', '/api/desktop/users/user-a' . $suffix)) {
                $selected = $route;
                break;
            }
        }
        checkPhoto($selected && [$selected['controller'], $selected['action']] === $expected
            && $selected['middleware'] === ['desktop_jwt']
            && $reflection->getProperty('routeParams')->getValue($router) === ['user-a'], 'Route/middleware/identity ' . $suffix);
    }
    $db->query('ALTER TABLE UsersDesktop ADD COLUMN Email VARCHAR(255) NULL');
    $initial = file_get_contents(__DIR__ . '/../database/schema.sql');
    preg_match('/CREATE TABLE `AttendancesDesktop` \(.*?ENGINE=InnoDB[^;]*;/s', $initial, $match);
    $db->query($match[0]);
    preg_match('/CREATE TABLE `SubscriptionPeriodDesktop` \(.*?ENGINE=InnoDB[^;]*;/s', $initial, $match);
    $db->query($match[0]);
    $db->query("INSERT INTO SubscriptionPeriodDesktop (Id, CustomerApiId, Name, Days, Price, CreatedOn, IsDeleted) VALUES
        ('period-a', 'gym-a', 'Mensual', 30, 999.99, '2026-01-01', 0),
        ('period-deleted', 'gym-a', 'Eliminado', 60, 888.88, '2026-01-01', 1),
        ('period-b', 'gym-b', 'Privado otro gimnasio', 90, 777.77, '2026-01-01', 0)");
    $db->query("INSERT INTO AttendancesDesktop (Id, CustomerApiId, UserId, CheckIn, Active, Removed) VALUES
        ('a-01', 'gym-a', 'user-a', '2026-01-01 00:00:00', 1, 0),
        ('a-02', 'gym-a', 'user-a', '2026-01-01 22:00:00', 0, 0),
        ('a-03', 'gym-a', 'user-a', '2026-01-02 12:00:00', 0, 0),
        ('a-04', 'gym-a', 'user-a', '2026-01-02 12:00:00', 1, 0),
        ('a-05', 'gym-a', 'user-a', '2026-01-03 23:59:59', 1, 0),
        ('a-06', 'gym-a', 'user-a', '2026-01-04 00:00:00', 0, 0),
        ('a-07', 'gym-a', 'user-a', '2026-01-03 23:59:59', 0, 1),
        ('a-08', 'gym-b', 'user-a', '2026-01-03 23:59:59', 0, 0),
        ('a-09', 'gym-a', 'user-active', '2026-01-01 23:00:00', 0, 0)");
    $db->query("INSERT INTO SubscriptionsDesktop (Id, CustomerApiId, UserId, StartDate, EndingDate, Removed) VALUES
        ('s-01', 'gym-a', 'user-a', ' 2026-01-01, 00;00;00 ', ' 2026-01-31, 23;59;59 ', 0),
        ('s-02', 'gym-a', 'user-a', '2026-01-02', '2026-02-01', 0),
        ('s-03', 'gym-a', 'user-a', '2026-01-02', '2026-02-02', 0),
        ('s-04', 'gym-a', 'user-a', '2026-01-03', '2026-02-03', 1),
        ('s-05', 'gym-b', 'user-a', '2026-01-03', '2026-02-03', 0)");

    // Todas las secciones y variantes aplican el mismo middleware y aislamiento del JWT.
    $queries = ['profile=user', 'profile=attendances', 'profile=subscriptions',
        'profile=statistics&from=2026-01-01&to=2026-01-03', 'variant=original'];
    $jwt = new App\Services\JwtService('temporary-photo-test-secret');
    $expired = $jwt->createToken(['customerId' => 'gym-a', 'name' => 'Temporary'], -1);
    $wrongType = $jwt->createToken(['customerId' => 'gym-a'], 60);
    foreach ($queries as $query) {
        checkPhoto(photoCgi($schema, $directory, 'GET', '', '', '', $query)['status'] === 401, 'Profile missing JWT ' . $query);
        checkPhoto(photoCgi($schema, $directory, 'GET', 'invalid', '', '', $query)['status'] === 401, 'Profile invalid JWT ' . $query);
        foreach ([$expired => 'TOKEN_EXPIRED', $wrongType => 'TOKEN_TYPE_INVALID'] as $badToken => $code) {
            $response = photoCgi($schema, $directory, 'GET', $badToken, '', '', $query);
            checkPhoto($response['status'] === 401 && json_decode($response['body'], true)['error_code'] === $code, 'Existing auth error ' . $code);
        }
        foreach (['user-a', 'missing', "user-a' OR '1'='1"] as $user) {
            checkPhoto(photoCgi($schema, $directory, 'GET', $otherToken, '', '', $query . '&userId=' . rawurlencode($user) . '&customerApiId=gym-a')['status'] === 404, 'No cross gym/id injection ' . $query);
        }
    }
    $user = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=user')['body'], true);
    checkPhoto(array_keys($user) === ['Id', 'Fullname', 'PhoneNumber', 'PhoneNumberEmergency', 'Gender', 'BirthDate', 'Code', 'Email', 'CreatedOn', 'photo', 'currentSubscription'], 'General fields allowlist');
    checkPhoto($user['Id'] === 'user-a' && $user['Email'] === null && $user['photo']['version'] === 3, 'User identity and photo contract');
    $noPhoto = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=user&userId=user-none')['body'], true);
    checkPhoto($noPhoto['photo'] === null && $noPhoto['currentSubscription'] === null, 'Absent photo and membership explicit null');

    $history = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=attendances&pageSize=2&page=2')['body'], true);
    checkPhoto($history === ['items' => [
        ['Id' => 'a-04', 'UserId' => 'user-a', 'CheckIn' => '2026-01-02 12:00:00', 'Active' => 1],
        ['Id' => 'a-03', 'UserId' => 'user-a', 'CheckIn' => '2026-01-02 12:00:00', 'Active' => 0]],
        'page' => 2, 'pageSize' => 2, 'total' => 6], 'Stable attendance pagination and isolation');
    $all = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=subscriptions')['body'], true);
    checkPhoto($all['pageSize'] === 25 && $all['total'] === 4 && count($all['items']) === 4, 'Full membership history includes older rows');
    $filtered = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=subscriptions&from=2026-01-01&to=2026-01-02')['body'], true);
    checkPhoto(array_column($filtered['items'], 'Id') === ['s-03', 's-02', 's-01'], 'Normalized dates inclusive and stable');
    checkPhoto($filtered['items'][2]['StartDate'] === '2026-01-01 00:00:00'
        && $filtered['items'][2]['EndingDate'] === '2026-01-31 23:59:59'
        && $filtered['items'][2]['Warning'] === false && is_numeric($filtered['items'][2]['Payment']), 'Membership value types and normalization');
    foreach (['profile=attendances&from=2026-01-04', 'profile=subscriptions&to=2026-01-01'] as $query) {
        $result = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['body'], true);
        checkPhoto($result['total'] === 1, 'Independent optional date bound');
    }
    $emptyPage = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=attendances&page=100')['body'], true);
    checkPhoto($emptyPage['items'] === [] && $emptyPage['total'] === 6, 'Empty page keeps total');
    foreach (['page=0', 'pageSize=101', 'page[]=1', 'page=1.5', 'from=2026-02-30', 'from=2026-01-03&to=2026-01-01', 'to[]=2026-01-01'] as $invalid) {
        checkPhoto(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=attendances&' . $invalid)['status'] === 422, 'Invalid filter ' . $invalid);
    }
    checkCurrentSubscriptionContract($db, $schema, $directory, $token);
    checkPhoto(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=statistics')['status'] === 422, 'Statistics require range');
    $stats = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=statistics&from=2026-01-01&to=2026-01-03')['body'], true);
    checkPhoto($stats['summary'] === ['totalAttempts' => 5, 'allowedAttempts' => 3, 'deniedAttempts' => 2, 'daysWithAllowedAccess' => 3], 'All attempts independent from latest daily attempts');
    checkPhoto($stats['daily'] === [
        ['date' => '2026-01-01', 'total' => 1, 'allowed' => 0, 'denied' => 1],
        ['date' => '2026-01-02', 'total' => 1, 'allowed' => 1, 'denied' => 0],
        ['date' => '2026-01-03', 'total' => 1, 'allowed' => 1, 'denied' => 0]], 'Latest timestamp and ID tie break, removed/foreign attempts excluded');
    checkPhoto(count($stats['byWeekday']) === 7 && count($stats['byHour']) === 24
        && $stats['byWeekday'][5]['total'] === 1 && $stats['byWeekday'][6]['total'] === 1
        && $stats['byHour'][12]['total'] === 1 && $stats['byHour'][23]['total'] === 1
        && array_sum(array_column($stats['byHour'], 'total')) === 2, 'Weekday/hour count only permitted latest attempts');
    $empty = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=statistics&from=2025-12-30&to=2025-12-31')['body'], true);
    checkPhoto($empty['summary']['totalAttempts'] === 0 && count($empty['daily']) === 2 && array_sum(array_column($empty['daily'], 'total')) === 0, 'Empty interval has complete zero series');

    $original = photoCgi($schema, $directory, 'GET', $token, '', '', 'variant=original');
    checkPhoto($original['body'] === $photos->original('gym-a', 'user-a')['bytes'] && getimagesizefromstring($original['body'])[0] === 600, 'Original preserves full resolution');
    checkPhoto(str_contains($original['headers'], 'Content-Type: image/png') && str_contains($original['headers'], 'private, no-store')
        && str_contains($original['headers'], 'nosniff') && str_contains($original['headers'], 'Content-Length: ' . strlen($original['body'])), 'Original protected headers and MIME');
    checkPhoto(photoCgi($schema, $directory, 'GET', $token, '', '', 'variant=original&userId=user-none')['status'] === 404, 'Missing photo fallback');
    $originalPath = $directory . '/' . $db->fetchOne("SELECT StorageKey FROM UserPhotosDesktop WHERE UserId='user-a'")['StorageKey'] . '.original';
    rename($originalPath, $originalPath . '.backup');
    try {
        checkPhoto(photoCgi($schema, $directory, 'GET', $token, '', '', 'variant=original')['status'] === 404, 'Absent original file fallback');
        checkPhoto(photoCgi($schema, $directory, 'GET', $token)['status'] === 200, 'Absent original does not break thumbnail');
    } finally {
        rename($originalPath . '.backup', $originalPath);
    }
    $image = imagecreatetruecolor(400, 200);
    ob_start(); imagejpeg($image); $jpg = ob_get_clean(); imagedestroy($image);
    $path = $directory . '/profile-jpeg.jpg';
    file_put_contents($path, $jpg);
    try {
        $payload = $photos->validate(['version' => '1', 'sha256' => hash('sha256', $jpg)], ['tmp_name' => $path, 'error' => UPLOAD_ERR_OK]);
        $photos->upload('gym-a', 'user-active', $payload);
        $jpeg = photoCgi($schema, $directory, 'GET', $token, '', '', 'variant=original&userId=user-active');
        checkPhoto($jpeg['body'] === $jpg && str_contains($jpeg['headers'], 'Content-Type: image/jpeg'), 'JPEG original MIME');
    } finally {
        unlink($path);
        $key = $db->fetchOne("SELECT StorageKey FROM UserPhotosDesktop WHERE UserId='user-active'")['StorageKey'] ?? null;
        $db->query("DELETE FROM UserPhotosDesktop WHERE UserId='user-active'");
        if ($key) { unlink($directory . '/' . $key . '.original'); unlink($directory . '/' . $key . '.png'); }
    }
    $db->query("UPDATE UsersDesktop SET Removed=1 WHERE Id='user-a'");
    try {
        foreach ($queries as $query) {
            checkPhoto(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['status'] === 404, 'Deleted member excluded ' . $query);
        }
    } finally {
        $db->query("UPDATE UsersDesktop SET Removed=0 WHERE Id='user-a'");
    }
    echo "Profile CGI: JWT/isolation, allowlist, histories/pagination, normalized dates, latest-attempt statistics, empty ranges, PNG/JPEG originals and removed members passed.\n";
}

/** Valida resumen independiente, fechas calendario e importes/periodos sin tocar datos fuera del esquema temporal. */
function checkCurrentSubscriptionContract(PhotoTestDatabase $db, string $schema, string $directory, string $token): void
{
    $db->query("INSERT INTO UsersDesktop (Id, CustomerApiId, Fullname, PhoneNumber, BirthDate) VALUES
        ('user-contract', 'gym-a', 'Temporary contract', '000', '2000-01-01')");
    $db->query('ALTER TABLE SubscriptionsDesktop MODIFY StartDate VARCHAR(50) NULL, MODIFY EndingDate VARCHAR(50) NULL');
    $db->query("INSERT INTO SubscriptionsDesktop
        (Id, CustomerApiId, UserId, StartDate, EndingDate, SubscriptionPeriodId, Payment, Removed) VALUES
        ('contract-01', 'gym-a', 'user-contract', '2032-01-01', ' 2030-01-31, 23;59;59 ', 'period-a', 123.45, 0),
        ('contract-02', 'gym-a', 'user-contract', NULL, '2030-01-31', 'period-a', 0, 0),
        ('contract-03', 'gym-a', 'user-contract', '2026-01-01', '2026-02-01', 'period-deleted', NULL, 0),
        ('contract-04', 'gym-a', 'user-contract', '2026-01-02', '2026-02-02', 'period-missing', 12.34, 0),
        ('contract-05', 'gym-a', 'user-contract', '2026-01-03', '2026-02-03', 'period-b', 5.67, 0),
        ('contract-removed', 'gym-a', 'user-contract', '', '2099-01-01', 'period-a', 1, 1),
        ('contract-foreign', 'gym-b', 'user-contract', '', '2099-01-01', 'period-b', 2, 0),
        ('contract-bad-01', 'gym-a', 'user-contract', '', '9999-02-30', NULL, 3, 0),
        ('contract-bad-02', 'gym-a', 'user-contract', '', '9999-00-31', NULL, 3, 0),
        ('contract-bad-03', 'gym-a', 'user-contract', '', '9999-01-00', NULL, 3, 0),
        ('contract-bad-04', 'gym-a', 'user-contract', '', 'not-a-date', NULL, 3, 0),
        ('contract-bad-05', 'gym-a', 'user-contract', '', '', NULL, 3, 0),
        ('contract-bad-06', 'gym-a', 'user-contract', '', NULL, NULL, 3, 0),
        ('contract-bad-07', 'gym-a', 'user-contract', '', '0000-01-01', NULL, 3, 0)");
    $query = 'profile=user&userId=user-contract';
    $detail = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['body'], true, 512, JSON_THROW_ON_ERROR);
    $current = $detail['currentSubscription'];
    checkPhoto($current['Id'] === 'contract-02' && $current['StartDate'] === null
        && $current['EndingDate'] === '2030-01-31' && $current['Payment'] === 0
        && $current['SubscriptionPeriodName'] === 'Mensual' && $current['SubscriptionPeriodDays'] === 30,
        'Current uses calendar EndingDate then Id, missing start and zero payment');
    checkPhoto(array_keys($current) === ['Id', 'UserId', 'StartDate', 'EndingDate', 'SubscriptionPeriodId', 'Payment',
        'Warning', 'Finished', 'Registered', 'SubscriptionPeriodName', 'SubscriptionPeriodDays'], 'Current membership allowlist');
    $historyQuery = 'profile=subscriptions&userId=user-contract';
    $history = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $historyQuery)['body'], true, 512, JSON_THROW_ON_ERROR);
    checkPhoto($history['total'] === 12 && count($history['items']) === 12, 'Missing/deleted/foreign periods do not hide memberships');
    $rows = array_column($history['items'], null, 'Id');
    checkPhoto($rows['contract-02'] === $current && $rows['contract-01']['Payment'] === 123.45
        && $rows['contract-01']['EndingDate'] === '2030-01-31 23:59:59', 'Shared summary/history normalization and decimal payment from record');
    foreach (['contract-03', 'contract-04', 'contract-05', 'contract-bad-01'] as $id) {
        checkPhoto($rows[$id]['SubscriptionPeriodName'] === null && $rows[$id]['SubscriptionPeriodDays'] === null,
            'Unavailable period remains explicit null ' . $id);
    }
    checkPhoto($rows['contract-03']['Payment'] === null && $rows['contract-04']['Payment'] === 12.34,
        'Absent amount differs from zero and catalog price');
    $page = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $historyQuery . '&pageSize=1')['body'], true);
    $filtered = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $historyQuery . '&from=2026-01-01&to=2026-01-03&pageSize=1&page=2')['body'], true);
    $again = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query . '&from=2026-01-01&to=2026-01-03&pageSize=1&page=2')['body'], true);
    checkPhoto($page['items'][0]['Id'] === 'contract-01' && $filtered['total'] === 3
        && $again['currentSubscription'] === $current, 'Detail independent from history order, filtering and pagination');
    $db->query("UPDATE SubscriptionsDesktop SET Removed=1 WHERE Id='contract-02'");
    $future = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['body'], true);
    checkPhoto($future['currentSubscription']['Id'] === 'contract-01'
        && $future['currentSubscription']['StartDate'] === '2032-01-01'
        && $future['currentSubscription']['Payment'] === 123.45, 'Future start still eligible');
    foreach (['contract-01' => 'contract-05', 'contract-05' => 'contract-04',
        'contract-04' => 'contract-03', 'contract-03' => null] as $id => $expected) {
        $db->update('SubscriptionsDesktop', ['Removed' => 1], 'Id = ?', [$id]);
        $result = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['body'], true);
        checkPhoto($result['currentSubscription'] === ($expected === null ? null : $rows[$expected]),
            'Current selection preserves full history contract after removal ' . $id);
        if ($result['currentSubscription'] !== null) {
            checkPhoto($result['currentSubscription']['SubscriptionPeriodName'] === null
                && $result['currentSubscription']['SubscriptionPeriodDays'] === null, 'Current survives unavailable period');
        }
    }
    checkPhoto($result['currentSubscription'] === null, 'Only invalid, removed or foreign endings yield null current');
    $unknown = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $historyQuery)['body'], true);
    checkPhoto($unknown['total'] === 7, 'Unknown expiration records distinguishable from no memberships');
    $db->insert('SubscriptionsDesktop', ['Id' => 'contract-leap', 'CustomerApiId' => 'gym-a', 'UserId' => 'user-contract',
        'StartDate' => '', 'EndingDate' => ' 2032-02-29, 00;00;00 ', 'Payment' => 0]);
    $leap = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', $query)['body'], true);
    checkPhoto($leap['currentSubscription']['Id'] === 'contract-leap'
        && $leap['currentSubscription']['EndingDate'] === '2032-02-29 00:00:00', 'Valid leap calendar date accepted');
    echo "Current subscription CGI: period isolation, missing catalogs, numeric payment, calendar validity, tie break and independent pagination passed.\n";
}
