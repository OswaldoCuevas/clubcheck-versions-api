<?php

/** Valida fotos y reglas del reporte de membresias con JWT, vista real y datos exclusivamente temporales. */
function checkMembershipPhotos(PhotoTestDatabase $db, App\Services\UserPhotoService $service, string $schema, string $directory, string $token, string $otherToken): void
{
    $initial = file_get_contents(__DIR__ . '/../database/schema.sql');
    preg_match('/CREATE TABLE `SubscriptionsDesktop` \(.*?ENGINE=InnoDB[^;]*;/s', $initial, $match);
    $db->query($match[0]);
    $view = file_get_contents(__DIR__ . '/../database/migrations/006_update_view_subscriptions_desktop.sql');
    $db->query('DROP VIEW IF EXISTS ViewSubscriptions');
    $db->query(substr($view, strpos($view, 'CREATE VIEW')));
    $db->query("INSERT INTO UsersDesktop (Id, CustomerApiId, Fullname, PhoneNumber, BirthDate, Sync) VALUES
        ('user-expired', 'gym-a', 'A expired', '000', '2000-01-01', 1),
        ('user-active', 'gym-a', 'B active', '000', '2000-01-01', 1),
        ('user-none', 'gym-a', 'C none', '000', '2000-01-01', 1)");
    foreach (['user-a' => 2, 'user-expired' => -1, 'user-active' => 10, 'user-b' => 1] as $userId => $days) {
        $db->insert('SubscriptionsDesktop', [
            'Id' => 'membership-' . $userId, 'UserId' => $userId,
            'CustomerApiId' => $userId === 'user-b' ? 'gym-b' : 'gym-a',
            'StartDate' => date('Y-m-d', strtotime('-30 days')),
            'EndingDate' => date('Y-m-d', strtotime($days . ' days')),
        ]);
    }
    // Id deliberadamente distinto de UserId: nunca buscar una foto por el identificador de membresia.
    $attached = $service->attach('gym-a', [
        ['Id' => 'user-b', 'UserId' => 'user-a'], ['Id' => 'user-a', 'UserId' => 'user-b'],
    ], 'UserId');
    checkPhoto($attached[0]['photo']['version'] === 3 && $attached[1]['photo'] === null, 'Membership photo uses UserId and JWT gym');
    checkPhoto(photoCgi($schema, $directory, 'GET', '', '', '', 'report=memberships')['status'] === 401, 'Membership report requires JWT');
    checkPhoto(photoCgi($schema, $directory, 'GET', 'invalid', '', '', 'report=memberships')['status'] === 401, 'Membership report rejects invalid JWT');
    $response = photoCgi($schema, $directory, 'GET', $token, '', '', 'report=memberships&sortBy=fullname');
    checkPhoto($response['status'] === 200, 'Membership report succeeds');
    $report = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    $rows = $report['memberships']['data'];
    checkPhoto(array_column($rows, 'UserId') === ['user-expired', 'user-active', 'user-none', 'user-a'], 'Membership ordering and gym isolation');
    $photo = $rows[3]['photo'];
    checkPhoto($photo === ['version' => 3, 'updatedAt' => $db->fetchOne("SELECT UpdatedAt FROM UserPhotosDesktop WHERE UserId='user-a'")['UpdatedAt']], 'Current metadata contract');
    checkPhoto($rows[0]['photo'] === null && $rows[1]['photo'] === null && $rows[2]['photo'] === null, 'Missing photo is explicitly null');
    checkPhoto($rows[3]['status'] === 'expiring' && $rows[3]['isExpiring'] === true && $rows[2]['status'] === 'none', 'Membership status aliases unchanged');
    checkPhoto($report['summary']['activeTotal'] === 2 && $report['summary']['activeToday'] === 2 && $report['summary']['expiringTotal'] === 1, 'Membership summary unchanged');
    foreach (['active' => ['user-a', 'user-active'], 'expired' => ['user-expired'], 'expiring' => ['user-a']] as $status => $expected) {
        $filtered = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'report=memberships&status=' . $status)['body'], true);
        checkPhoto(array_column($filtered['memberships']['data'], 'UserId') === $expected, 'Membership filter ' . $status);
        checkPhoto($filtered['memberships']['pagination']['total'] === count($expected), 'Filtered pagination ' . $status);
        checkPhoto($filtered['summary']['activeTotal'] === 2, 'Summary independent from filter');
    }
    foreach (['expiration', 'endingDate'] as $sort) {
        $sorted = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'report=memberships&sortBy=' . $sort . '&sortDir=desc')['body'], true);
        checkPhoto(array_column($sorted['memberships']['data'], 'UserId') === ['user-active', 'user-a', 'user-expired', 'user-none'], 'Descending sort ' . $sort);
    }
    $page = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'report=memberships&sortBy=fullname&page=2&perPage=2')['body'], true);
    checkPhoto(array_column($page['memberships']['data'], 'UserId') === ['user-none', 'user-a'], 'Membership page contents');
    checkPhoto($page['memberships']['pagination'] === ['page' => 2, 'perPage' => 2, 'total' => 4, 'totalPages' => 2], 'Membership pagination unchanged');
    $empty = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'report=memberships&page=10')['body'], true);
    checkPhoto($empty['memberships']['data'] === [] && $empty['memberships']['pagination']['total'] === 4, 'Empty page supported');
    $other = json_decode(photoCgi($schema, $directory, 'GET', $otherToken, '', '', 'report=memberships&customerApiId=gym-a')['body'], true);
    checkPhoto(array_column($other['memberships']['data'], 'UserId') === ['user-b'] && $other['memberships']['data'][0]['photo'] === null, 'JWT gym overrides supplied gym');
    // Descarga exactamente el UserId recibido en el reporte, no Id ni SubscriptionId.
    $download = photoCgi($schema, $directory, 'GET', $token, '', '', 'userId=' . $rows[3]['UserId']);
    checkPhoto($download['status'] === 200 && $download['body'] === $service->thumbnail('gym-a', 'user-a'), 'Report to protected blob flow');
    checkPhoto(photoCgi($schema, $directory, 'GET', $otherToken, '', '', 'userId=' . $rows[3]['UserId'])['status'] === 404, 'Report photo download gym isolation');
    checkPhoto(photoCgi($schema, $directory, 'GET', $token, '', '', 'userId=user-none')['status'] === 404, 'Missing photo permits avatar fallback');
    $db->query("UPDATE UserPhotosDesktop SET Version=4 WHERE UserId='user-a'");
    $updated = json_decode(photoCgi($schema, $directory, 'GET', $token, '', '', 'report=memberships&status=expiring')['body'], true);
    checkPhoto($updated['memberships']['data'][0]['photo']['version'] === 4, 'New photo version visible in report');
    $db->query("UPDATE UserPhotosDesktop SET Version=3 WHERE UserId='user-a'");
    echo "Membership CGI contract: UserId/photo, JWT isolation, protected blob, missing photo, new version, filters, ordering, summary and pagination passed.\n";
}
