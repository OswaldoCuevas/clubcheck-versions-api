<?php
namespace App\Services;

require_once __DIR__ . '/JwtService.php';
require_once __DIR__ . '/../Exceptions/MemberException.php';

use App\Exceptions\MemberException;

/** Dominio global members. Nunca crea socios ni escribe tablas sincronizadas. Fechas UTC. */
class MembersService
{
    private $db;
    private JwtService $jwt;
    private string $key;
    private string $web;
    private $sendMail;

    /** Inyecta persistencia/transporte para probar el proceso sin correo ni datos reales. */
    public function __construct($db = null, ?callable $sendMail = null)
    {
        $secret = (string) getenv('MEMBERS_JWT_SECRET');
        $this->key = (string) getenv('MEMBERS_INVITATION_KEY');
        $this->web = rtrim((string) getenv('MEMBERS_WEB_URL'), '/');
        $parts = parse_url($this->web);
        if (strlen($secret) < 32 || strlen($this->key) < 32 || $secret === $this->key
            || $secret === ($_ENV['JWT_SECRET_KEY'] ?? getenv('JWT_SECRET_KEY') ?: 'default-secret-key-change-in-production')
            || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new MemberException('La configuración members no está disponible.', 503, [], 'MEMBERS_UNAVAILABLE');
        }
        if ($db === null) {
            require_once __DIR__ . '/../../utils/database.php';
            $db = new \Database();
        }
        $this->db = $db;
        $this->jwt = new JwtService($secret);
        $this->sendMail = $sendMail ?? static function (string $email, string $url): bool {
            require_once __DIR__ . '/EmailService.php';
            return (new EmailService())->sendMemberVerification($email, $url);
        };
    }

    /** Genera identificadores UUID v4 para entidades y referencias opacas del flujo. */
    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 15) | 64);
        $b[8] = chr((ord($b[8]) & 63) | 128);
        $h = bin2hex($b);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
    }

    /** Normaliza correo ASCII único; rechaza conversiones implícitas de datos de entrada. */
    public function email(array $data): string
    {
        $email = strtolower($this->text($data, 'Email', 254));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[^\x20-\x7e]/', $email)) {
            $this->fail('INVALID_INPUT', 'Correo inválido.', 400);
        }
        return $email;
    }

    /** Valida campos del contrato y evita arrays, truncamiento o cuerpos ambiguos. */
    public function text(array $data, string $field, int $max = 255): string
    {
        if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '' || strlen($data[$field]) > $max) {
            $this->fail('INVALID_INPUT', 'Campo inválido: ' . $field, 400);
        }
        return trim($data[$field]);
    }

    /** Aplica límites persistentes y atómicos; usa REMOTE_ADDR, nunca headers de proxy no confiables. */
    public function limit(string $operation, string $subject, int $maximum, int $seconds): void
    {
        $bucket = hash_hmac('sha256', $operation . '|' . $subject . '|' . intdiv(time(), $seconds), $this->key);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->execute_query('INSERT INTO MemberRateLimits (Id, BucketHash, Attempts, ExpiresAt, Active, IsDeleted, CreatedAt, UpdatedAt) VALUES (?, ?, 1, ?, 1, 0, ?, ?) ON DUPLICATE KEY UPDATE Attempts = Attempts + 1, UpdatedAt = VALUES(UpdatedAt)',
            [self::uuid(), $bucket, gmdate('Y-m-d H:i:s', time() + $seconds), $now, $now]);
        $row = $this->db->fetchOne('SELECT Attempts FROM MemberRateLimits WHERE BucketHash = ?', [$bucket]);
        if ((int) $row['Attempts'] > $maximum) {
            $this->fail('RATE_LIMITED', 'Demasiados intentos. Intenta más tarde.', 429);
        }
    }

    /** Registra una cuenta sin crear socios; conserva una invitación vigente para otro dispositivo. */
    public function register(array $data): array
    {
        $this->limit('register', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 5, 3600);
        $name = $this->text($data, 'Name', 150);
        $email = $this->email($data);
        $password = $data['Password'] ?? null;
        if (!is_string($password) || strlen($password) < 10 || strlen($password) > 72) {
            $this->fail('INVALID_INPUT', 'La contraseña debe tener entre 10 y 72 bytes.', 400);
        }
        $pending = isset($data['InvitationContextId']) ? $this->text($data, 'InvitationContextId', 36) : null;
        $id = self::uuid();
        $this->transaction(function () use ($id, $name, $email, $password, $pending): void {
            if ($pending !== null) {
                $context = $this->contextRecord($pending, true);
                if ($context['MemberAccountId'] !== null) {
                    $this->fail('INVITATION_CONFLICT', 'El contexto ya pertenece a una cuenta.', 409);
                }
            }
            $this->create('MemberAccounts', ['Id' => $id, 'Name' => $name, 'Email' => $email,
                'PasswordHash' => password_hash($password, PASSWORD_DEFAULT), 'PendingInvitationContextId' => $pending], $id);
            if ($pending !== null) {
                $this->change('MemberInvitationContexts', ['MemberAccountId' => $id], 'Id = ?', [$pending], $id);
            }
        });
        // La cuenta se conserva aunque falle SMTP; el usuario puede reenviar confirmación.
        $this->resend(['Email' => $email]);
        return ['Code' => 'EMAIL_CONFIRMATION_REQUIRED', 'Message' => 'Confirma tu correo para iniciar sesión.'];
    }

    /** Emite verificación de un solo uso; limita reenvíos sin revelar si existe una cuenta. */
    public function resend(array $data): array
    {
        $email = $this->email($data);
        $this->limit('resend-ip', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 15, 3600);
        $this->limit('resend-email', $email, 3, 3600);
        $token = bin2hex(random_bytes(32));
        $shouldSend = $this->transaction(function () use ($email, $token): bool {
            $account = $this->db->fetchOne('SELECT * FROM MemberAccounts WHERE Email = ? FOR UPDATE', [$email]);
            if (!$account || !$account['Active'] || $account['IsDeleted'] || $account['EmailConfirmedAt']) {
                return false;
            }
            $recent = $this->db->fetchOne('SELECT CreatedAt FROM MemberEmailVerifications WHERE MemberAccountId = ? ORDER BY CreatedAt DESC LIMIT 1', [$account['Id']]);
            if ($recent && strtotime($recent['CreatedAt'] . ' UTC') > time() - 60) {
                $this->fail('RATE_LIMITED', 'Espera un minuto antes de reenviar.', 429);
            }
            $this->change('MemberEmailVerifications', ['Active' => 0], 'MemberAccountId = ?', [$account['Id']], $account['Id']);
            $this->create('MemberEmailVerifications', ['MemberAccountId' => $account['Id'], 'TokenHash' => hash('sha256', $token),
                'ExpiresAt' => gmdate('Y-m-d H:i:s', time() + 86400)], $account['Id']);
            return true;
        });
        if ($shouldSend) {
            try {
                $sent = ($this->sendMail)($email, $this->web . '/verify-email#token=' . $token);
            } catch (\Throwable $e) {
                $sent = false;
            }
            if (!$sent) {
                $this->fail('MAIL_UNAVAILABLE', 'La cuenta se conserva. No fue posible enviar el correo; reintenta más tarde.', 503);
            }
        }
        return ['Message' => 'Si corresponde, recibirás un correo de confirmación.'];
    }

    /** Confirma correo por POST sin sesión privada; el GET del enlace nunca consume tokens. */
    public function verifyEmail(array $data): array
    {
        $this->limit('verify', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 20, 600);
        $hash = hash('sha256', $this->text($data, 'Token', 128));
        $this->transaction(function () use ($hash): void {
            $record = $this->db->fetchOne('SELECT * FROM MemberEmailVerifications WHERE TokenHash = ?', [$hash]);
            if (!$record) {
                $this->fail('VERIFICATION_EXPIRED', 'Confirmación vencida o utilizada.', 410);
            }
            $account = $this->db->fetchOne('SELECT * FROM MemberAccounts WHERE Id = ? FOR UPDATE', [$record['MemberAccountId']]);
            $record = $this->db->fetchOne('SELECT * FROM MemberEmailVerifications WHERE TokenHash = ? FOR UPDATE', [$hash]);
            if (!$account || !$account['Active'] || $account['IsDeleted'] || !$record['Active'] || $record['IsDeleted']
                || $record['ConsumedAt'] || strtotime($record['ExpiresAt'] . ' UTC') <= time()) {
                $this->fail('VERIFICATION_EXPIRED', 'Confirmación vencida o utilizada.', 410);
            }
            $this->change('MemberAccounts', ['EmailConfirmedAt' => gmdate('Y-m-d H:i:s')], 'Id = ?', [$account['Id']], $account['Id']);
            $this->change('MemberEmailVerifications', ['Active' => 0, 'ConsumedAt' => gmdate('Y-m-d H:i:s')], 'Id = ?', [$record['Id']], $account['Id']);
        });
        return ['Message' => 'Correo confirmado. Inicia sesión para completar tu vinculación.'];
    }

    /** Autentica exclusivamente cuentas confirmadas y crea sesión revocable con expiración fija. */
    public function login(array $data): array
    {
        $email = $this->email($data);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->limit('login-ip', $ip, 60, 600);
        $this->limit('login-email', $email, 10, 600);
        $password = $this->text($data, 'Password', 72);
        // No recortar contraseñas: espacios son parte de la credencial.
        $password = $data['Password'];
        $account = $this->db->fetchOne('SELECT * FROM MemberAccounts WHERE Email = ?', [$email]);
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        if (!password_verify($password, $account['PasswordHash'] ?? $dummy) || !$account || !$account['Active'] || $account['IsDeleted']) {
            $this->fail('INVALID_CREDENTIALS', 'Credenciales inválidas.', 401);
        }
        if (!$account['EmailConfirmedAt']) {
            $this->fail('EMAIL_UNCONFIRMED', 'Confirma tu correo antes de acceder.', 403);
        }
        $session = $this->create('MemberSessions', ['MemberAccountId' => $account['Id'], 'ExpiresAt' => gmdate('Y-m-d H:i:s', time() + 3600)], $account['Id']);
        return $this->sessionResponse($account, $session, time() + 3600);
    }

    /** Verifica audiencia, sesión y cuenta en cada petición; opcionalmente valida el contexto vigente. */
    public function authenticate(string $token, bool $checkContext = true): array
    {
        $payload = $this->jwt->validateToken($token);
        if (!$payload || ($payload['aud'] ?? '') !== 'clubcheck-members' || ($payload['typ'] ?? '') !== 'members'
            || empty($payload['sub']) || empty($payload['sid']) || ($payload['exp'] ?? 0) <= time()) {
            $this->fail('SESSION_INVALID', 'Sesión inválida o vencida.', 401);
        }
        $session = $this->db->fetchOne('SELECT * FROM MemberSessions WHERE Id = ? AND MemberAccountId = ? AND Active = 1 AND IsDeleted = 0', [$payload['sid'], $payload['sub']]);
        $account = $this->db->fetchOne('SELECT * FROM MemberAccounts WHERE Id = ? AND Active = 1 AND IsDeleted = 0', [$payload['sub']]);
        if (!$session || !$account || strtotime($session['ExpiresAt'] . ' UTC') <= time()) {
            $this->fail('SESSION_INVALID', 'Sesión inválida o vencida.', 401);
        }
        if (!$account['EmailConfirmedAt']) {
            $this->fail('EMAIL_UNCONFIRMED', 'Correo pendiente de confirmar.', 403);
        }
        if ($checkContext && isset($payload['LinkId'])) {
            $link = $this->authorizedLink($account['Id'], $payload['LinkId']);
            if ($link['UserId'] !== ($payload['UserId'] ?? null) || $link['CustomerApiId'] !== ($payload['CustomerApiId'] ?? null)) {
                $this->fail('SESSION_INVALID', 'Contexto inválido.', 401);
            }
        }
        return ['Account' => $account, 'Payload' => $payload, 'Session' => $session];
    }

    /** Revoca la sesión completa, incluidos todos los JWT de contexto emitidos con ella. */
    public function logout(array $identity): array
    {
        $this->change('MemberSessions', ['Active' => 0], 'Id = ?', [$identity['Session']['Id']], $identity['Account']['Id']);
        return ['Message' => 'Sesión cerrada.'];
    }

    /** Devuelve identidad global y continuidad pendiente sin exponer hashes ni contraseñas. */
    public function accountView(array $account): array
    {
        return ['Id' => $account['Id'], 'Name' => $account['Name'], 'Email' => $account['Email'],
            'EmailConfirmed' => $account['EmailConfirmedAt'] !== null, 'PendingInvitationContextId' => $account['PendingInvitationContextId']];
    }

    /** Lista todos los vínculos propios; conserva estados deshabilitados y no inventa logos. */
    public function links(string $accountId): array
    {
        $rows = $this->db->fetchAll('SELECT l.Id, l.UserId, l.CustomerApiId, u.Fullname AS MemberName, c.Name AS GymName, l.Active, l.IsDeleted, u.Removed, c.IsActive AS GymActive FROM MemberAccountLinks l LEFT JOIN UsersDesktop u ON u.Id = l.UserId AND u.CustomerApiId = l.CustomerApiId LEFT JOIN Customers c ON c.Id = l.CustomerApiId WHERE l.MemberAccountId = ? ORDER BY l.CreatedAt, l.Id', [$accountId]);
        return ['Items' => array_map(static function (array $r): array {
            $active = (bool) $r['Active'] && !$r['IsDeleted'] && $r['MemberName'] !== null && !$r['Removed'] && (bool) $r['GymActive'];
            return ['Id' => $r['Id'], 'UserId' => $r['UserId'], 'CustomerApiId' => $r['CustomerApiId'], 'MemberName' => $r['MemberName'],
                'GymName' => $r['GymName'], 'GymLogoUrl' => null, 'Active' => $active];
        }, $rows)];
    }

    /** Cambia contexto conservando caducidad y sesión; un vínculo bloqueado no invalida la cuenta global. */
    public function selectContext(array $identity, array $data): array
    {
        $link = $this->authorizedLink($identity['Account']['Id'], $this->text($data, 'LinkId', 36));
        return $this->sessionResponse($identity['Account'], $identity['Session']['Id'], strtotime($identity['Session']['ExpiresAt'] . ' UTC'), $link);
    }

    /** Captura secreto o código por POST sin consumir la invitación; limita fotografía/reutilización. */
    public function invitationContext(array $data): array
    {
        $this->limit('invitation-context', $_SERVER['REMOTE_ADDR'] ?? 'unknown', 20, 600);
        if (isset($data['Secret']) === isset($data['Code'])) {
            $this->fail('INVALID_INPUT', 'Proporciona Secret o Code.', 400);
        }
        $isCode = isset($data['Code']);
        $value = $this->text($data, $isCode ? 'Code' : 'Secret', $isCode ? 10 : 64);
        $value = $isCode ? strtoupper($value) : $value;
        $column = $isCode ? 'CodeHash' : 'SecretHash';
        $inv = $this->db->fetchOne("SELECT * FROM MemberLinkInvitations WHERE {$column} = ?", [hash('sha256', $value)]);
        $this->validInvitation($inv);
        $id = $this->create('MemberInvitationContexts', ['InvitationId' => $inv['Id']]);
        $user = $this->db->fetchOne('SELECT u.Fullname, c.Name FROM UsersDesktop u JOIN Customers c ON c.Id = u.CustomerApiId WHERE u.Id = ? AND u.CustomerApiId = ? AND COALESCE(u.Removed, 0) = 0 AND c.IsActive = 1', [$inv['UserId'], $inv['CustomerApiId']]);
        if (!$user) {
            $this->fail('INVITATION_EXPIRED', 'Socio o gimnasio no disponible.', 410);
        }
        return ['InvitationContextId' => $id, 'MemberName' => $user['Fullname'], 'GymName' => $user['Name'], 'ExpiresAt' => $this->iso($inv['ExpiresAt'])];
    }

    /** Canje transaccional: bloquea socio e invitación y conserva titularidad incluso deshabilitada. */
    public function redeem(array $identity, array $data): array
    {
        $accountId = $identity['Account']['Id'];
        $contextId = $this->text($data, 'InvitationContextId', 36);
        $this->limit('redeem', $accountId, 30, 600);
        return $this->transaction(function () use ($accountId, $contextId): array {
            $context = $this->db->fetchOne('SELECT x.*, i.UserId, i.CustomerApiId FROM MemberInvitationContexts x JOIN MemberLinkInvitations i ON i.Id = x.InvitationId WHERE x.Id = ?', [$contextId]);
            if (!$context) {
                $this->fail('INVITATION_EXPIRED', 'Invitación no disponible.', 410);
            }
            $this->lockUser($context['UserId'], $context['CustomerApiId']);
            $inv = $this->db->fetchOne('SELECT * FROM MemberLinkInvitations WHERE Id = ? FOR UPDATE', [$context['InvitationId']]);
            $bound = $this->db->fetchOne('SELECT * FROM MemberInvitationContexts WHERE Id = ? FOR UPDATE', [$contextId]);
            if (!$bound['Active'] || $bound['IsDeleted'] || ($bound['MemberAccountId'] !== null && $bound['MemberAccountId'] !== $accountId)) {
                $this->fail('INVITATION_CONFLICT', 'El contexto no pertenece a esta cuenta.', 409);
            }
            $existing = $this->db->fetchOne('SELECT * FROM MemberAccountLinks WHERE UserId = ? AND CustomerApiId = ? FOR UPDATE', [$inv['UserId'], $inv['CustomerApiId']]);
            if ($existing && (!$existing['Active'] || $existing['IsDeleted'])) {
                $this->fail('LINK_DISABLED', 'La relación está deshabilitada; una invitación no permite rehabilitarla.', 403);
            }
            // Un reintento del mismo canje confirmado devuelve la relación original.
            if ($inv['ConsumedAt'] && $inv['MemberAccountId'] === $accountId && $existing && $existing['MemberAccountId'] === $accountId) {
                return ['LinkId' => $existing['Id']];
            }
            $this->validInvitation($inv);
            if ($existing) {
                $this->fail('MEMBER_ALREADY_OWNED', 'El socio ya tiene titular.', 409);
            }
            $linkId = $this->create('MemberAccountLinks', ['MemberAccountId' => $accountId, 'UserId' => $inv['UserId'], 'CustomerApiId' => $inv['CustomerApiId']], $accountId);
            $this->change('MemberLinkInvitations', ['Active' => 0, 'ConsumedAt' => gmdate('Y-m-d H:i:s'), 'MemberAccountId' => $accountId], 'Id = ?', [$inv['Id']], $accountId);
            $this->change('MemberInvitationContexts', ['MemberAccountId' => $accountId], 'Id = ?', [$contextId], $accountId);
            $this->change('MemberAccounts', ['PendingInvitationContextId' => null], 'Id = ? AND PendingInvitationContextId = ?', [$accountId, $contextId], $accountId);
            return ['LinkId' => $linkId];
        });
    }

    /** Emite QR/código equivalentes; RequestId repite el resultado y otro RequestId regenera explícitamente. */
    public function issue(array $operator, array $data): array
    {
        $this->requirePermission($operator);
        $userId = $this->text($data, 'UserId', 36);
        $requestId = $this->text($data, 'RequestId', 64);
        $this->limit('issue', $operator['Id'], 60, 600);
        return $this->transaction(function () use ($operator, $userId, $requestId): array {
            $this->lockUser($userId, $operator['CustomerApiId']);
            $inv = $this->db->fetchOne('SELECT * FROM MemberLinkInvitations WHERE CustomerApiId = ? AND RequestId = ? FOR UPDATE', [$operator['CustomerApiId'], $requestId]);
            if ($inv) {
                if ($inv['UserId'] !== $userId || $inv['CreatedBy'] !== $operator['Id']) {
                    $this->fail('REQUEST_CONFLICT', 'RequestId ya utilizado para otra operación.', 409);
                }
                $this->validInvitation($inv);
            } else {
                if ($this->db->fetchOne('SELECT Id FROM MemberAccountLinks WHERE UserId = ? AND CustomerApiId = ?', [$userId, $operator['CustomerApiId']])) {
                    $this->fail('MEMBER_ALREADY_OWNED', 'El socio ya tiene titular, incluso si la relación está deshabilitada.', 409);
                }
                $this->change('MemberLinkInvitations', ['Active' => 0], 'UserId = ? AND CustomerApiId = ? AND Active = 1', [$userId, $operator['CustomerApiId']], $operator['Id']);
                $id = self::uuid();
                $credentials = $this->invitationCredentials($id);
                $inv = ['Id' => $id, 'UserId' => $userId, 'CustomerApiId' => $operator['CustomerApiId'], 'RequestId' => $requestId,
                    'SecretHash' => hash('sha256', $credentials['Secret']), 'CodeHash' => hash('sha256', $credentials['Code']),
                    'ExpiresAt' => gmdate('Y-m-d H:i:s', time() + 900)];
                $this->create('MemberLinkInvitations', $inv, $operator['Id']);
            }
            $credentials = $this->invitationCredentials($inv['Id']);
            $names = $this->db->fetchOne('SELECT u.Fullname, c.Name FROM UsersDesktop u JOIN Customers c ON c.Id = u.CustomerApiId WHERE u.Id = ? AND u.CustomerApiId = ?', [$userId, $operator['CustomerApiId']]);
            return ['InvitationId' => $inv['Id'], 'Code' => $credentials['Code'], 'Url' => $this->web . '/invite#secret=' . $credentials['Secret'],
                'ExpiresAt' => $this->iso($inv['ExpiresAt']), 'MemberName' => $names['Fullname'], 'GymName' => $names['Name']];
        });
    }

    /** Autentica portal existente o sesión individual de escritorio, siempre consultando administrador actual. */
    public function operator(string $token): array
    {
        $payload = (new JwtService())->validateToken($token);
        if (!$payload || ($payload['exp'] ?? 0) <= time()) {
            $this->fail('SESSION_INVALID', 'Sesión administrativa inválida.', 401);
        }
        if (($payload['typ'] ?? '') === 'member_operator') {
            if (($payload['aud'] ?? '') !== 'clubcheck-member-management') {
                $this->fail('SESSION_INVALID', 'Audiencia administrativa inválida.', 401);
            }
            $session = $this->db->fetchOne('SELECT * FROM MemberOperatorSessions WHERE Id = ? AND Active = 1 AND IsDeleted = 0', [$payload['sid'] ?? '']);
            if (!$session || strtotime($session['ExpiresAt'] . ' UTC') <= time()) {
                $this->fail('SESSION_INVALID', 'Sesión individual vencida.', 401);
            }
            $adminId = $session['AdministratorId'];
            $customer = $session['CustomerApiId'];
        } elseif (empty($payload['typ']) && !empty($payload['adminId']) && !empty($payload['customerId']) && !empty($payload['name'])) {
            $adminId = $payload['adminId'];
            $customer = $payload['customerId'];
        } else {
            $this->fail('SESSION_INVALID', 'Se requiere identidad individual de portal o escritorio.', 401);
        }
        $admin = $this->db->fetchOne('SELECT a.* FROM AdministratorsDesktop a JOIN Customers c ON c.Id = a.CustomerApiId WHERE a.Id = ? AND a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0 AND c.IsActive = 1', [$adminId, $customer]);
        if (!$admin) {
            $this->fail('SESSION_INVALID', 'Administrador no disponible.', 401);
        }
        return $admin;
    }

    /** Acredita operador con contraseña sincronizada además del JWT de máquina validado previamente. */
    public function operatorSession(string $customerId, array $data): array
    {
        $this->limit('operator-login', $customerId . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 600);
        $adminId = $this->text($data, 'AdministratorId', 36);
        $password = $this->text($data, 'Password', 255);
        $password = $data['Password'];
        $admin = $this->db->fetchOne('SELECT a.* FROM AdministratorsDesktop a JOIN Customers c ON c.Id = a.CustomerApiId WHERE a.Id = ? AND a.CustomerApiId = ? AND COALESCE(a.Removed, 0) = 0 AND c.IsActive = 1', [$adminId, $customerId]);
        $stored = (string) ($admin['Password'] ?? '');
        // Compatibilidad explícita con las tres representaciones usadas por el login del portal.
        $info = password_get_info($stored);
        if (($info['algo'] ?? null) !== null) {
            $valid = password_verify($password, $stored);
        } elseif (preg_match('/^[a-f0-9]{64}$/i', $stored)) {
            $valid = hash_equals(strtolower($stored), hash('sha256', $password));
        } else {
            $valid = $stored !== '' && hash_equals($stored, $password);
        }
        if (!$admin || !$valid) {
            $this->fail('INVALID_CREDENTIALS', 'Credenciales individuales inválidas.', 401);
        }
        $this->requirePermission($admin);
        $id = $this->create('MemberOperatorSessions', ['AdministratorId' => $adminId, 'CustomerApiId' => $customerId,
            'ExpiresAt' => gmdate('Y-m-d H:i:s', time() + 900)], $adminId);
        return ['Token' => (new JwtService())->createToken(['typ' => 'member_operator', 'aud' => 'clubcheck-member-management', 'sid' => $id], 900),
            'ExpiresAt' => gmdate('Y-m-d\TH:i:s\Z', time() + 900)];
    }

    /** Lista cuentas paginadas y exclusivamente relaciones del gimnasio del dueño autenticado. */
    public function accounts(array $operator, array $query): array
    {
        $this->requireOwner($operator);
        $page = filter_var($query['Page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        $size = filter_var($query['PageSize'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $search = $query['Search'] ?? '';
        if ($page === false || $size === false || !is_string($search) || strlen($search) > 150) {
            $this->fail('INVALID_INPUT', 'Paginación o búsqueda inválida.', 400);
        }
        $where = 'a.IsDeleted = 0 AND EXISTS (SELECT 1 FROM MemberAccountLinks l WHERE l.MemberAccountId = a.Id AND l.CustomerApiId = ?) AND (LOCATE(?, a.Name) > 0 OR LOCATE(?, a.Email) > 0)';
        $params = [$operator['CustomerApiId'], trim($search), trim($search)];
        $total = $this->db->fetchOne("SELECT COUNT(*) AS Total FROM MemberAccounts a WHERE {$where}", $params);
        $rows = $this->db->fetchAll("SELECT a.* FROM MemberAccounts a WHERE {$where} ORDER BY a.Name, a.Id LIMIT ? OFFSET ?", array_merge($params, [$size, ($page - 1) * $size]));
        $items = [];
        foreach ($rows as $row) {
            $local = $this->db->fetchAll('SELECT l.Id, l.UserId, l.CustomerApiId, u.Fullname AS MemberName, CASE WHEN l.Active = 1 AND l.IsDeleted = 0 AND u.Id IS NOT NULL AND COALESCE(u.Removed, 0) = 0 THEN 1 ELSE 0 END AS Active FROM MemberAccountLinks l LEFT JOIN UsersDesktop u ON u.Id = l.UserId AND u.CustomerApiId = l.CustomerApiId WHERE l.MemberAccountId = ? AND l.CustomerApiId = ? ORDER BY l.Id', [$row['Id'], $operator['CustomerApiId']]);
            foreach ($local as &$link) { $link['Active'] = (bool) $link['Active']; }
            unset($link);
            $items[] = ['Id' => $row['Id'], 'Name' => $row['Name'], 'Email' => $row['Email'], 'EmailConfirmed' => $row['EmailConfirmedAt'] !== null, 'Links' => $local];
        }
        return ['Items' => $items, 'Page' => $page, 'PageSize' => $size, 'Total' => (int) $total['Total']];
    }

    /** Deshabilita solamente el vínculo local; conserva titularidad, cuenta y acceso físico. */
    public function disable(array $operator, string $linkId, array $data): array
    {
        $this->requireOwner($operator);
        if (!array_key_exists('Active', $data) || $data['Active'] !== false) {
            $this->fail('INVALID_INPUT', 'Esta fase solo permite Active:false.', 400);
        }
        $link = $this->db->fetchOne('SELECT Id FROM MemberAccountLinks WHERE Id = ? AND CustomerApiId = ?', [$linkId, $operator['CustomerApiId']]);
        if (!$link) {
            $this->fail('PERMISSION_DENIED', 'Relación no disponible en este gimnasio.', 403);
        }
        $this->change('MemberAccountLinks', ['Active' => 0], 'Id = ? AND CustomerApiId = ?', [$linkId, $operator['CustomerApiId']], $operator['Id']);
        return ['Id' => $linkId, 'Active' => false];
    }

    /** Comprueba permiso explícito vigente; ni Role ni la máquina conceden emisión implícita. */
    private function requirePermission(array $operator): void
    {
        $permission = $this->db->fetchOne("SELECT Id FROM MemberOperatorPermissions WHERE AdministratorId = ? AND CustomerApiId = ? AND Permission = 'MemberInvitations.Issue' AND Active = 1 AND IsDeleted = 0", [$operator['Id'], $operator['CustomerApiId']]);
        if (!$permission) {
            $this->fail('PERMISSION_DENIED', 'Se requiere el permiso MemberInvitations.Issue.', 403);
        }
    }

    /** Publica capacidades del portal desde persistencia; no sustituye la autorización por operación. */
    public static function operatorCapabilities($db, array $operator): array
    {
        $permission = $db->fetchOne("SELECT Id FROM MemberOperatorPermissions WHERE AdministratorId = ? AND CustomerApiId = ? AND Permission = 'MemberInvitations.Issue' AND Active = 1 AND IsDeleted = 0", [$operator['Id'], $operator['CustomerApiId']]);
        return ['IsOwner' => (int) ($operator['Role'] ?? 0) === 2,
            'Permissions' => $permission ? ['MemberInvitations.Issue'] : []];
    }

    /** Usa Role=2 persistido, como el proceso existente de resolución por dueño. */
    private function requireOwner(array $operator): void
    {
        if ((int) ($operator['Role'] ?? 0) !== 2) {
            $this->fail('OWNER_REQUIRED', 'Solo el dueño puede administrar relaciones.', 403);
        }
    }

    /** Valida relación propia y estados actuales de socio/gimnasio antes de usar contexto. */
    private function authorizedLink(string $accountId, string $linkId): array
    {
        $row = $this->db->fetchOne('SELECT l.* FROM MemberAccountLinks l JOIN UsersDesktop u ON u.Id = l.UserId AND u.CustomerApiId = l.CustomerApiId JOIN Customers c ON c.Id = l.CustomerApiId WHERE l.Id = ? AND l.MemberAccountId = ? AND l.Active = 1 AND l.IsDeleted = 0 AND COALESCE(u.Removed, 0) = 0 AND c.IsActive = 1', [$linkId, $accountId]);
        if (!$row) {
            $this->fail('LINK_DISABLED', 'Relación deshabilitada o no autorizada. Selecciona otra relación.', 403);
        }
        return $row;
    }

    /** Exige los tres claims del JWT y revalida titularidad y disponibilidad para recursos del socio. */
    public function requireContext(array $identity): array
    {
        $payload = $identity['Payload'];
        foreach (['LinkId', 'UserId', 'CustomerApiId'] as $key) {
            if (!isset($payload[$key]) || !is_string($payload[$key]) || $payload[$key] === '') {
                $this->fail('CONTEXT_REQUIRED', 'Selecciona una relación para continuar.', 400);
            }
        }
        $link = $this->authorizedLink($identity['Account']['Id'], $payload['LinkId']);
        if ($link['UserId'] !== $payload['UserId'] || $link['CustomerApiId'] !== $payload['CustomerApiId']) {
            $this->fail('SESSION_INVALID', 'Contexto inválido.', 401);
        }
        return $link;
    }

    /** Produce JWT con identidad y contexto opcional sin ampliar la sesión original. */
    private function sessionResponse(array $account, string $sessionId, int $expires, ?array $link = null): array
    {
        $payload = ['aud' => 'clubcheck-members', 'typ' => 'members', 'sub' => $account['Id'], 'Name' => $account['Name'], 'sid' => $sessionId, 'exp' => $expires];
        if ($link !== null) {
            $payload += ['LinkId' => $link['Id'], 'UserId' => $link['UserId'], 'CustomerApiId' => $link['CustomerApiId']];
        }
        return ['Token' => $this->jwt->createToken($payload, max(1, $expires - time())), 'ExpiresAt' => gmdate('Y-m-d\TH:i:s\Z', $expires),
            'Account' => $this->accountView($account), 'PendingInvitationContextId' => $account['PendingInvitationContextId']];
    }

    /** Obtiene continuidad vigente sin prolongar el vencimiento de la invitación original. */
    private function contextRecord(string $id, bool $lock = false): array
    {
        $row = $this->db->fetchOne('SELECT x.*, i.ExpiresAt, i.Active AS InvitationActive, i.IsDeleted AS InvitationDeleted, i.ConsumedAt FROM MemberInvitationContexts x JOIN MemberLinkInvitations i ON i.Id = x.InvitationId WHERE x.Id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$row || !$row['Active'] || $row['IsDeleted'] || !$row['InvitationActive'] || $row['InvitationDeleted'] || $row['ConsumedAt'] || strtotime($row['ExpiresAt'] . ' UTC') <= time()) {
            $this->fail('INVITATION_EXPIRED', 'Invitación vencida o invalidada. Solicita una nueva.', 410);
        }
        return $row;
    }

    /** Bloquea el socio sincronizado para serializar emisión/canje incluso sin vínculos previos. */
    private function lockUser(string $userId, string $customerId): void
    {
        $row = $this->db->fetchOne('SELECT Id FROM UsersDesktop WHERE Id = ? AND CustomerApiId = ? AND COALESCE(Removed, 0) = 0 FOR UPDATE', [$userId, $customerId]);
        $gym = $this->db->fetchOne('SELECT Id FROM Customers WHERE Id = ? AND IsActive = 1', [$customerId]);
        if (!$row || !$gym) {
            $this->fail('MEMBER_NOT_SYNCED', 'El socio debe estar sincronizado y disponible en este gimnasio.', 409);
        }
    }

    /** Rechaza invitaciones vencidas, invalidadas o consumidas independientemente de su representación. */
    private function validInvitation(?array $inv): void
    {
        if (!$inv || !$inv['Active'] || $inv['IsDeleted'] || $inv['ConsumedAt'] || strtotime($inv['ExpiresAt'] . ' UTC') <= time()) {
            $this->fail('INVITATION_EXPIRED', 'Invitación vencida, utilizada o invalidada. Solicita una nueva.', 410);
        }
    }

    /** Deriva secretos seudorrandom reproducibles para reintentos; persistencia almacena únicamente hashes. */
    private function invitationCredentials(string $id): array
    {
        $bytes = hash_hmac('sha256', 'code|' . $id, $this->key, true);
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $code = '';
        // 31 símbolos sin I/O/0/1; rechazo evita sesgo de módulo.
        $counter = 0;
        while (strlen($code) < 10) {
            foreach (unpack('C*', $bytes) as $byte) {
                if ($byte < 248 && strlen($code) < 10) { $code .= $alphabet[$byte % 31]; }
            }
            $bytes = hash_hmac('sha256', 'code|' . $id . '|' . ++$counter, $this->key, true);
        }
        return ['Secret' => hash_hmac('sha256', 'secret|' . $id, $this->key), 'Code' => $code];
    }

    /** Inserta entidades con UUID, estados y auditoría uniforme en los procesos members. */
    private function create(string $table, array $data, ?string $actor = null): string
    {
        $data += ['Id' => self::uuid(), 'Active' => 1, 'IsDeleted' => 0, 'CreatedAt' => gmdate('Y-m-d H:i:s'),
            'UpdatedAt' => gmdate('Y-m-d H:i:s'), 'CreatedBy' => $actor, 'UpdatedBy' => $actor];
        $this->db->insert($table, $data);
        return $data['Id'];
    }

    /** Actualiza estados con trazabilidad sin tocar datos de sincronización. */
    private function change(string $table, array $data, string $where, array $params, ?string $actor): void
    {
        $data['UpdatedAt'] = gmdate('Y-m-d H:i:s');
        $data['UpdatedBy'] = $actor;
        $this->db->update($table, $data, $where, $params);
    }

    /** Garantiza atomicidad y convierte carreras de unicidad en conflicto y deadlocks en reintento explícito. */
    private function transaction(callable $work)
    {
        $this->db->begin_transaction();
        try {
            $result = $work();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollback();
            if ($e instanceof \mysqli_sql_exception && $e->getCode() === 1062) {
                $this->fail('OWNERSHIP_CONFLICT', 'El correo, solicitud o socio ya está asignado.', 409);
            }
            if ($e instanceof \mysqli_sql_exception && in_array($e->getCode(), [1205, 1213], true)) {
                $this->fail('TEMPORARILY_UNAVAILABLE', 'Operación concurrente; reintenta la misma solicitud.', 503);
            }
            throw $e;
        }
    }

    /** Expone fechas UTC ISO-8601 en el contrato compartido. */
    private function iso(string $date): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', strtotime($date . ' UTC'));
    }

    /** Interrumpe el proceso con códigos de negocio estables para todos los clientes. */
    private function fail(string $code, string $message, int $status): void
    {
        throw new MemberException($message, $status, [], $code);
    }
}
