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
    checkPhoto(array_keys($user) === ['Id', 'Fullname', 'PhoneNumber', 'PhoneNumberEmergency', 'Gender', 'BirthDate', 'Code', 'Email', 'CreatedOn', 'photo'], 'General fields allowlist');
    checkPhoto($user['Id'] === 'user-a' && $user['Email'] === null && $user['photo']['version'] === 3, 'User identity and photo contract');
    $noPhoto = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'profile=user&userId=user-none')['body'], true);
    checkPhoto($noPhoto['photo'] === null, 'Absent photo metadata explicit null');

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
