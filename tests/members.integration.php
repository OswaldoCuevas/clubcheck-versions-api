<?php
// Ejecutar: php tests/members.integration.php. Solo crea y elimina clubcheck_members_test_<hex>.
require_once __DIR__ . '/../app/Services/MembersService.php';
require_once __DIR__ . '/../utils/database.php';

use App\Services\MembersService;
use App\Services\JwtService;
use App\Exceptions\MemberException;

/** Configura conexiones exclusivamente al esquema temporal, nunca al .env del proyecto. */
function testEnvironment(string $schema): void
{
    if (!preg_match('/^clubcheck_members_test_[a-f0-9]{12}$/', $schema)) { throw new RuntimeException('Unsafe schema'); }
    $values = ['XDEBUG_MODE' => 'off', 'APP_MODE' => 'DEV', 'APP_DEBUG' => 'false', 'DEV_DB_HOST' => '127.0.0.1', 'DEV_DB_PORT' => '3306',
        'DEV_DB_DATABASE' => $schema, 'DEV_DB_USERNAME' => getenv('MEMBERS_TEST_DB_USER') ?: 'root',
        'DEV_DB_PASSWORD' => getenv('MEMBERS_TEST_DB_PASSWORD') ?: '', 'MEMBERS_JWT_SECRET' => str_repeat('members-test-', 4),
        'MEMBERS_INVITATION_KEY' => str_repeat('invite-test-', 4), 'MEMBERS_WEB_URL' => 'https://localhost:8100',
        'JWT_SECRET_KEY' => str_repeat('administrative-test-', 3), 'MEMBERS_CORS_ORIGINS' => 'https://localhost:8100',
        'MAIL_HOST' => '', 'MAIL_USER' => '', 'MAIL_PASSWORD' => '', 'MAIL_FROM_ADDRESS' => ''];
    foreach ($values as $k => $v) { putenv($k . '=' . $v); $_ENV[$k] = $v; }
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

/** Falla inmediatamente cuando un resultado de negocio no coincide con lo esperado. */
function check(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

/** Comprueba código y estado HTTP de rechazos sin aceptar errores técnicos como éxito. */
function rejects(callable $operation, string $code, int $status): void
{
    try { $operation(); } catch (MemberException $e) {
        check($e->getErrorCode() === $code && $e->getStatusCode() === $status, 'Expected ' . $code . ', got ' . $e->getErrorCode());
        return;
    }
    throw new RuntimeException('Expected rejection ' . $code);
}

// Los workers conectan al mismo esquema aislado para probar transacciones simultáneas reales.
if (($argv[1] ?? '') === '--worker') {
    testEnvironment($argv[2]);
    $svc = new MembersService(new Database(), static fn() => true);
    try {
        $result = $svc->redeem($svc->authenticate($argv[3], false), ['InvitationContextId' => $argv[4]]);
        echo json_encode($result);
    } catch (MemberException $e) { echo json_encode($e->toArray()); }
    exit;
}

$schema = 'clubcheck_members_test_' . bin2hex(random_bytes(6));
testEnvironment($schema);
$root = new mysqli('127.0.0.1', getenv('DEV_DB_USERNAME'), getenv('DEV_DB_PASSWORD'), '', 3306);
$root->query('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4');
$server = null;
$pipes = [];
$serverLog = null;
try {
    $db = new Database();
    $db->query('CREATE TABLE Customers (Id VARCHAR(64) PRIMARY KEY, Name VARCHAR(150), IsActive TINYINT, Token VARCHAR(64), TokenJwt TEXT, LastSeen DATETIME) ENGINE=InnoDB');
    $db->query('CREATE TABLE UsersDesktop (Id VARCHAR(36) PRIMARY KEY, CustomerApiId VARCHAR(64), Fullname VARCHAR(150), Removed TINYINT DEFAULT 0) ENGINE=InnoDB');
    $db->query('CREATE TABLE AdministratorsDesktop (Id VARCHAR(36) PRIMARY KEY, CustomerApiId VARCHAR(64), Password VARCHAR(255), Role INT, Removed TINYINT DEFAULT 0) ENGINE=InnoDB');
    $sql = file_get_contents(__DIR__ . '/../database/migrations/017_create_member_accounts.sql');
    $db->multi_query($sql);
    do { if ($r = $db->store_result()) { $r->free(); } } while ($db->more_results() && $db->next_result());
    // La misma migración es idempotente.
    $db->multi_query($sql);
    do { if ($r = $db->store_result()) { $r->free(); } } while ($db->more_results() && $db->next_result());
    $mail = [];
    $svc = new MembersService($db, static function (string $email, string $url) use (&$mail): bool { $mail[$email] = substr($url, strpos($url, '#token=') + 7); return true; });
    $admins = []; $users = [];
    for ($i = 1; $i <= 3; $i++) {
        $gym = 'gym-' . $i; $admin = MembersService::uuid(); $user = MembersService::uuid();
        $db->insert('Customers', ['Id' => $gym, 'Name' => 'Gym ' . $i, 'IsActive' => 1]);
        $db->insert('AdministratorsDesktop', ['Id' => $admin, 'CustomerApiId' => $gym, 'Password' => hash('sha256', 'operator-password'), 'Role' => 2]);
        $db->insert('UsersDesktop', ['Id' => $user, 'CustomerApiId' => $gym, 'Fullname' => 'Socio ' . $i]);
        $db->insert('MemberOperatorPermissions', ['Id' => MembersService::uuid(), 'AdministratorId' => $admin, 'CustomerApiId' => $gym,
            'Permission' => 'MemberInvitations.Issue', 'CreatedAt' => gmdate('Y-m-d H:i:s'), 'UpdatedAt' => gmdate('Y-m-d H:i:s')]);
        $admins[] = ['Id' => $admin, 'CustomerApiId' => $gym, 'Role' => 2]; $users[] = $user;
    }
    $inv = $svc->issue($admins[0], ['UserId' => $users[0], 'RequestId' => 'request-one']);
    check($svc->issue($admins[0], ['UserId' => $users[0], 'RequestId' => 'request-one']) === $inv, 'Idempotent emission');
    $secret = substr($inv['Url'], strpos($inv['Url'], '#secret=') + 8);
    $ctx = $svc->invitationContext(['Secret' => $secret]);
    $codeCtx = $svc->invitationContext(['Code' => strtolower($inv['Code'])]);
    check($ctx['MemberName'] === $codeCtx['MemberName'], 'QR/code equivalence');
    $svc->register(['Name' => 'Titular', 'Email' => ' GLOBAL@example.test ', 'Password' => ' password123 ', 'InvitationContextId' => $ctx['InvitationContextId']]);
    rejects(fn() => $svc->login(['Email' => 'global@example.test', 'Password' => ' password123 ']), 'EMAIL_UNCONFIRMED', 403);
    rejects(fn() => $svc->resend(['Email' => 'global@example.test']), 'RATE_LIMITED', 429);
    $svc->verifyEmail(['Token' => $mail['global@example.test']]);
    rejects(fn() => $svc->verifyEmail(['Token' => $mail['global@example.test']]), 'VERIFICATION_EXPIRED', 410);
    $login = $svc->login(['Email' => 'global@example.test', 'Password' => ' password123 ']);
    $identity = $svc->authenticate($login['Token']);
    check($login['Account']['PendingInvitationContextId'] === $ctx['InvitationContextId'], 'Server continuity');
    $link = $svc->redeem($identity, ['InvitationContextId' => $login['Account']['PendingInvitationContextId']]);
    check($svc->redeem($identity, ['InvitationContextId' => $ctx['InvitationContextId']]) === $link, 'Idempotent redemption');
    rejects(fn() => $svc->invitationContext(['Code' => $inv['Code']]), 'INVITATION_EXPIRED', 410);
    rejects(fn() => $svc->register(['Name' => 'Duplicate', 'Email' => 'GLOBAL@example.test', 'Password' => 'password123']), 'OWNERSHIP_CONFLICT', 409);
    for ($i = 1; $i < 3; $i++) {
        $other = $svc->issue($admins[$i], ['UserId' => $users[$i], 'RequestId' => 'multi-' . $i]);
        $otherCtx = $svc->invitationContext(['Code' => $other['Code']]);
        $svc->redeem($identity, $otherCtx);
    }
    check(count($svc->links($identity['Account']['Id'])['Items']) === 3, 'Three gyms');
    $local = $svc->accounts($admins[0], ['Page' => 1, 'PageSize' => 1, 'Search' => 'Titular']);
    check($local['Total'] === 1 && count($local['Items'][0]['Links']) === 1 && $local['Items'][0]['Links'][0]['CustomerApiId'] === 'gym-1', 'Tenant isolation');
    rejects(fn() => $svc->accounts($admins[0], ['PageSize' => 101]), 'INVALID_INPUT', 400);
    $selected = $svc->selectContext($identity, ['LinkId' => $link['LinkId']]);
    $svc->authenticate($selected['Token']);
    rejects(fn() => $svc->disable($admins[1], $link['LinkId'], ['Active' => false]), 'PERMISSION_DENIED', 403);
    rejects(fn() => $svc->disable($admins[0], $link['LinkId'], ['Active' => true]), 'INVALID_INPUT', 400);
    $svc->disable($admins[0], $link['LinkId'], ['Active' => false]);
    rejects(fn() => $svc->authenticate($selected['Token']), 'LINK_DISABLED', 403);
    $svc->authenticate($selected['Token'], false);
    $remaining = $svc->links($identity['Account']['Id'])['Items'];
    check(count(array_filter($remaining, static fn($r) => $r['Active'])) === 2, 'Other links survive');
    $enabled = array_values(array_filter($remaining, static fn($r) => $r['Active']));
    $svc->selectContext($identity, ['LinkId' => $enabled[0]['Id']]);
    rejects(fn() => $svc->issue($admins[0], ['UserId' => $users[0], 'RequestId' => 'cannot-transfer']), 'MEMBER_ALREADY_OWNED', 409);
    rejects(fn() => $svc->redeem($identity, $ctx), 'LINK_DISABLED', 403);
    $adminToken = (new JwtService())->createToken(['customerId' => 'gym-1', 'name' => 'Gym 1', 'adminId' => $admins[0]['Id'], 'role' => 0]);
    $svc->operator($adminToken);
    rejects(fn() => $svc->authenticate($adminToken), 'SESSION_INVALID', 401);
    rejects(fn() => $svc->operator($login['Token']), 'SESSION_INVALID', 401);
    $machineToken = (new JwtService())->createToken(['typ' => 'customer', 'cid' => 'gym-1', 'mkt' => 'machine']);
    rejects(fn() => $svc->operator($machineToken), 'SESSION_INVALID', 401);
    $opLogin = $svc->operatorSession('gym-1', ['AdministratorId' => $admins[0]['Id'], 'Password' => 'operator-password']);
    check($svc->operator($opLogin['Token'])['Id'] === $admins[0]['Id'], 'Individual desktop identity');
    rejects(fn() => $svc->operatorSession('gym-1', ['AdministratorId' => $admins[0]['Id'], 'Password' => hash('sha256', 'operator-password')]), 'INVALID_CREDENTIALS', 401);
    $db->update('MemberOperatorPermissions', ['Active' => 0], 'AdministratorId = ?', [$admins[0]['Id']]);
    rejects(fn() => $svc->issue($svc->operator($adminToken), ['UserId' => $users[0], 'RequestId' => 'denied']), 'PERMISSION_DENIED', 403);
    rejects(fn() => $svc->issue($svc->operator($opLogin['Token']), ['UserId' => $users[0], 'RequestId' => 'denied']), 'PERMISSION_DENIED', 403);
    $db->update('MemberOperatorPermissions', ['Active' => 1], 'AdministratorId = ?', [$admins[0]['Id']]);
    $db->update('AdministratorsDesktop', ['Role' => 0], 'Id = ?', [$admins[0]['Id']]);
    rejects(fn() => $svc->accounts($svc->operator($adminToken), []), 'OWNER_REQUIRED', 403);
    $db->update('AdministratorsDesktop', ['Role' => 2], 'Id = ?', [$admins[0]['Id']]);
    rejects(fn() => $svc->issue($admins[0], ['UserId' => 'not-synced', 'RequestId' => 'missing']), 'MEMBER_NOT_SYNCED', 409);

    // Caducidad y regeneración preservan la cuenta y la referencia pendiente, sin canje silencioso.
    $extraUser = MembersService::uuid();
    $db->insert('UsersDesktop', ['Id' => $extraUser, 'CustomerApiId' => 'gym-1', 'Fullname' => 'Extra']);
    $old = $svc->issue($admins[0], ['UserId' => $extraUser, 'RequestId' => 'old']);
    $new = $svc->issue($admins[0], ['UserId' => $extraUser, 'RequestId' => 'new']);
    rejects(fn() => $svc->invitationContext(['Code' => $old['Code']]), 'INVITATION_EXPIRED', 410);
    rejects(fn() => $svc->issue($admins[0], ['UserId' => $extraUser, 'RequestId' => 'old']), 'INVITATION_EXPIRED', 410);
    $pending = $svc->invitationContext(['Code' => $new['Code']]);
    $svc->register(['Name' => 'Second', 'Email' => 'second@example.test', 'Password' => 'password123', 'InvitationContextId' => $pending['InvitationContextId']]);
    $db->update('MemberLinkInvitations', ['ExpiresAt' => '2000-01-01 00:00:00'], 'Id = ?', [$new['InvitationId']]);
    $svc->verifyEmail(['Token' => $mail['second@example.test']]);
    $secondLogin = $svc->login(['Email' => 'second@example.test', 'Password' => 'password123']);
    $second = $svc->authenticate($secondLogin['Token']);
    rejects(fn() => $svc->redeem($second, $ctx), 'INVITATION_CONFLICT', 409);
    rejects(fn() => $svc->redeem($second, $pending), 'INVITATION_EXPIRED', 410);
    check($second['Account']['Name'] === 'Second', 'Expired invitation preserves account');
    $fresh = $svc->issue($admins[0], ['UserId' => $extraUser, 'RequestId' => 'fresh']);
    $freshCtx = $svc->invitationContext(['Code' => $fresh['Code']]);
    $freshSecret = substr($fresh['Url'], strpos($fresh['Url'], '#secret=') + 8);
    $freshOtherCtx = $svc->invitationContext(['Secret' => $freshSecret]);

    // Dos procesos compiten por el mismo socio; exactamente uno obtiene titularidad.
    $db->begin_transaction();
    $db->fetchOne('SELECT Id FROM UsersDesktop WHERE Id = ? FOR UPDATE', [$extraUser]);
    $workers = [];
    foreach ([[$login['Token'], $freshCtx['InvitationContextId']], [$secondLogin['Token'], $freshOtherCtx['InvitationContextId']]] as [$token, $workerContext]) {
        $wp = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $schema, $token, $workerContext], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $wp, dirname(__DIR__));
        check(is_resource($process), 'Concurrent worker start');
        fclose($wp[0]); $workers[] = [$process, $wp];
    }
    usleep(300000);
    $db->commit();
    $successes = 0;
    foreach ($workers as [$process, $wp]) {
        $output = stream_get_contents($wp[1]); $err = stream_get_contents($wp[2]);
        fclose($wp[1]); fclose($wp[2]);
        check(proc_close($process) === 0 && $err === '', 'Worker failed: ' . $err);
        $result = json_decode($output, true);
        if (isset($result['LinkId'])) { $successes++; } else { check(in_array($result['Code'] ?? '', ['INVITATION_EXPIRED', 'INVITATION_CONFLICT'], true), 'Concurrent loser rejected: ' . $output); }
    }
    check($successes === 1, 'Exactly one concurrent owner');
    check((int) $db->fetchOne('SELECT COUNT(*) AS C FROM MemberAccountLinks WHERE UserId = ?', [$extraUser])['C'] === 1, 'Persistent ownership uniqueness');

    // Fallo del transporte conserva cuenta, nunca entrega sesión privada.
    $failedMail = new MembersService($db, static fn() => false);
    rejects(fn() => $failedMail->register(['Name' => 'Mail fail', 'Email' => 'mailfail@example.test', 'Password' => 'password123']), 'MAIL_UNAVAILABLE', 503);
    check($db->fetchOne('SELECT Id FROM MemberAccounts WHERE Email = ?', ['mailfail@example.test']) !== null, 'SMTP failure preserves account');
    for ($i = 0; $i < 20; $i++) {
        try { $svc->invitationContext(['Code' => 'AAAAAAAAAA']); } catch (MemberException $e) { check(in_array($e->getErrorCode(), ['INVITATION_EXPIRED', 'RATE_LIMITED']), 'Rate error'); }
    }
    rejects(fn() => $svc->invitationContext(['Code' => 'AAAAAAAAAA']), 'RATE_LIMITED', 429);
    $svc->logout($identity);
    rejects(fn() => $svc->authenticate($selected['Token'], false), 'SESSION_INVALID', 401);
    $svc->authenticate($secondLogin['Token']);
    echo "PASS: migration, email, sessions, QR/code, continuation, expiry, regeneration, permissions, three gyms, isolation, revocation and concurrent ownership.\n";

    // Valida el transporte reutilizado con PHPMailer sustituido; no sale correo ni se registra Body.
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../app/Services/EmailService.php';
    $transport = new class extends \PHPMailer\PHPMailer\PHPMailer {
        public string $captured = '';
        /** Simula entrega SMTP para comprobar contenido sin red ni persistencia del secreto. */
        public function send() { $this->captured = $this->Body; return true; }
    };
    $emailService = new \App\Services\EmailService();
    $property = new ReflectionProperty($emailService, 'mailer'); $property->setValue($emailService, $transport);
    $property = new ReflectionProperty($emailService, 'config');
    $property->setValue($emailService, ['host' => 'fake', 'fromAddress' => 'fake@example.test', 'username' => 'fake', 'password' => 'fake']);
    check($emailService->sendMemberVerification('target@example.test', 'https://members.example.test/verify-email#token=fake-secret'), 'Reused email transport');
    check(str_contains($transport->captured, '#token=fake-secret') && $transport->Body === '' && $transport->AltBody === '' && $transport->getToAddresses() === [], 'Transient mail secrets cleared');
    $transport->captured = '';
    echo "PASS: reused SMTP adapter with fake PHPMailer, no verification body persistence and cleared message secrets.\n";

    // HTTP real contra el front controller de producción, con configuración de prueba heredada.
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    check($socket !== false, 'Find local port');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $serverLog = tempnam(__DIR__, '.members-http-');
    $server = proc_open([PHP_BINARY, '-d', 'variables_order=EGPCS', '-S', $address, '-t', dirname(__DIR__) . '/public', dirname(__DIR__) . '/public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes, dirname(__DIR__));
    check(is_resource($server), 'HTTP server start');
    fclose($pipes[0]);
    for ($i = 0; $i < 40; $i++) {
        $ready = @fsockopen('127.0.0.1', (int) substr(strrchr($address, ':'), 1), $errno, $error, 0.1);
        if ($ready) { fclose($ready); break; } usleep(50000);
    }
    $httpLogin = request($address, 'POST', '/api/members/login', ['Email' => 'second@example.test', 'Password' => 'password123']);
    check($httpLogin['Status'] === 200 && isset($httpLogin['Body']['Token']), 'HTTP login: ' . json_encode($httpLogin));
    $httpToken = $httpLogin['Body']['Token'];
    check(request($address, 'GET', '/api/members/me', null, $httpToken)['Body']['Account']['Name'] === 'Second', 'HTTP restore');
    check(request($address, 'GET', '/api/members/links', null, $adminToken)['Status'] === 401, 'HTTP admin rejected in members');
    check(request($address, 'GET', '/api/member-management/accounts', null, $httpToken)['Status'] === 401, 'HTTP members rejected in management');
    $httpAccounts = request($address, 'GET', '/api/member-management/accounts', null, $adminToken);
    check(($httpAccounts['Body']['Total'] ?? 0) >= 1, 'HTTP owner list: ' . json_encode($httpAccounts));
    check(request($address, 'POST', '/api/members/context', ['LinkId' => $link['LinkId']], $httpToken)['Status'] === 403, 'HTTP unauthorized context');
    check(request($address, 'PATCH', '/api/member-management/links/' . $link['LinkId'], ['Active' => false], $adminToken)['Body']['Active'] === false, 'HTTP disable');
    check(request($address, 'POST', '/api/members/verify-email', ['Token' => $mail['second@example.test']])['Status'] === 410, 'HTTP one use verification');
    check(request($address, 'GET', '/api/members/verify-email', null)['Status'] === 404, 'GET cannot consume');
    check(request($address, 'POST', '/api/members/logout', [], $httpToken)['Status'] === 200, 'HTTP logout');
    check(request($address, 'GET', '/api/members/me', null, $httpToken)['Status'] === 401, 'HTTP session revoked');
    // Nuevo flujo HTTP: fallo de correo conserva registro; reenvío inyectado permite confirmar sin red.
    $db->query('DELETE FROM MemberRateLimits');
    $httpUser = MembersService::uuid();
    $db->insert('UsersDesktop', ['Id' => $httpUser, 'CustomerApiId' => 'gym-1', 'Fullname' => 'HTTP Socio']);
    $httpInvitation = request($address, 'POST', '/api/member-management/invitations', ['UserId' => $httpUser, 'RequestId' => 'http-flow'], $adminToken);
    check($httpInvitation['Status'] === 200 && isset($httpInvitation['Body']['Code']), 'HTTP issuance');
    $httpCtx = request($address, 'POST', '/api/members/invitation-context', ['Code' => $httpInvitation['Body']['Code']]);
    check($httpCtx['Status'] === 200, 'HTTP invitation capture');
    $httpRegistration = request($address, 'POST', '/api/members/register', ['Name' => 'HTTP Titular', 'Email' => 'http@example.test', 'Password' => 'password123', 'InvitationContextId' => $httpCtx['Body']['InvitationContextId']]);
    check($httpRegistration['Status'] === 503 && $httpRegistration['Body']['Code'] === 'MAIL_UNAVAILABLE', 'HTTP SMTP unavailable: ' . json_encode($httpRegistration));
    check(request($address, 'POST', '/api/members/login', ['Email' => 'http@example.test', 'Password' => 'password123'])['Body']['Code'] === 'EMAIL_UNCONFIRMED', 'HTTP unconfirmed denied');
    $httpAccount = $db->fetchOne('SELECT Id FROM MemberAccounts WHERE Email = ?', ['http@example.test']);
    $db->update('MemberEmailVerifications', ['CreatedAt' => '2000-01-01 00:00:00'], 'MemberAccountId = ?', [$httpAccount['Id']]);
    $svc->resend(['Email' => 'http@example.test']);
    check(request($address, 'POST', '/api/members/verify-email', ['Token' => $mail['http@example.test']])['Status'] === 200, 'HTTP email confirmation');
    $httpAccountLogin = request($address, 'POST', '/api/members/login', ['Email' => 'http@example.test', 'Password' => 'password123'])['Body'];
    check($httpAccountLogin['Account']['PendingInvitationContextId'] === $httpCtx['Body']['InvitationContextId'], 'HTTP server continuity');
    $httpRedeem = request($address, 'POST', '/api/members/links', ['InvitationContextId' => $httpAccountLogin['PendingInvitationContextId']], $httpAccountLogin['Token']);
    check($httpRedeem['Status'] === 200 && isset($httpRedeem['Body']['LinkId']), 'HTTP automatic redemption');
    check(request($address, 'POST', '/api/members/links', ['InvitationContextId' => $httpAccountLogin['PendingInvitationContextId']], $httpAccountLogin['Token'])['Body'] === $httpRedeem['Body'], 'HTTP redemption retry');
    check(request($address, 'POST', '/api/members/context', ['LinkId' => $httpRedeem['Body']['LinkId']], $httpAccountLogin['Token'])['Status'] === 200, 'HTTP selected context');
    $db->update('Customers', ['Token' => 'machine', 'TokenJwt' => $machineToken], 'Id = ?', ['gym-1']);
    $httpOperator = request($address, 'POST', '/api/member-management/operator-session', ['AdministratorId' => $admins[0]['Id'], 'Password' => 'operator-password'], $machineToken);
    check($httpOperator['Status'] === 200 && isset($httpOperator['Body']['Token']), 'HTTP individual operator: ' . json_encode($httpOperator));
    $preflight = request($address, 'OPTIONS', '/api/member-management/invitations', null);
    check($preflight['Status'] === 204 && str_contains(implode('|', $preflight['Headers']), 'https://members.example.test'), 'Configured CORS');
    echo "PASS: HTTP production routing, login, restore, token separation, accounts, context, disable, verification, logout and CORS.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } } proc_close($server); }
    if ($serverLog !== null) { unlink($serverLog); }
    if (isset($db)) { $db->close(); }
    $root->query('DROP DATABASE `' . $schema . '`');
    $root->close();
}

/** Ejecuta peticiones HTTP locales y conserva estado, JSON y cabeceras para validar el contrato. */
function request(string $address, string $method, string $path, ?array $body, string $token = ''): array
{
    $headers = ['Content-Type: application/json', 'Origin: https://members.example.test'];
    if ($token !== '') { $headers[] = 'Authorization: Bearer ' . $token; }
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers),
        'content' => $body === null ? '' : json_encode((object) $body), 'ignore_errors' => true, 'timeout' => 10]]);
    $content = file_get_contents('http://' . $address . $path, false, $context);
    preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $status);
    return ['Status' => (int) $status[1], 'Body' => json_decode($content, true), 'Headers' => $http_response_header, 'Raw' => $content];
}
