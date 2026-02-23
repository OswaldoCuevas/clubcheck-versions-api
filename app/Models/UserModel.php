<?php

namespace Models;

// Incluir la clase base Model
require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class UserModel extends Model
{
    private array $authConfig = [];

    protected function initialize()
    {
        $authConfigPath = __DIR__ . '/../../config/auth.php';

        if (file_exists($authConfigPath)) {
            $this->authConfig = require $authConfigPath;
        }

        $this->authConfig += [
            'session_name' => 'clubcheck_session',
            'remember_me_duration' => 30 * 24 * 60 * 60,
            'max_login_attempts' => 5,
            'lockout_duration' => 15 * 60,
            'password_min_length' => 8,
            'session_lifetime' => 7200,
        ];

        if (!headers_sent() && session_status() === PHP_SESSION_NONE && !empty($this->authConfig['session_name'])) {
            session_name($this->authConfig['session_name']);
        }
    }

    public function authenticate($username, $password, $rememberMe = false)
    {
        $username = trim((string) $username);

        if ($username === '' || $password === '') {
            $this->errors['login'][] = 'Credenciales incorrectas';
            return false;
        }

        if ($this->isLockedOut($username)) {
            $this->errors['login'][] = 'Cuenta bloqueada por intentos fallidos. Intenta más tarde.';
            return false;
        }

        $user = $this->findUser($username);

        if (!$user) {
            $this->recordLoginAttempt($username, false);
            $this->errors['login'][] = 'Credenciales incorrectas';
            return false;
        }

        if (!password_verify($password, $user['PasswordHash'])) {
            $this->recordLoginAttempt($username, false);
            $this->errors['login'][] = 'Credenciales incorrectas';
            return false;
        }

        if (!(bool) $user['IsActive']) {
            $this->errors['login'][] = 'Cuenta desactivada';
            return false;
        }

        $this->clearFailedAttempts($username);
        $this->recordLoginAttempt($username, true);

        $this->createSession($user, $rememberMe);
        $this->updateLastLogin((int) $user['Id']);

        $this->log("Usuario {$username} inició sesión", 'info');

        return true;
    }

    private function createSession(array $user, bool $rememberMe): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $roles = $this->getUserRoles((int) $user['Id']);
        $permissions = $this->getPermissionsForUser((int) $user['Id']);

        $_SESSION['user'] = [
            'id' => (int) $user['Id'],
            'username' => $user['Username'],
            'role' => $roles[0] ?? null,
            'roles' => $roles,
            'permissions' => $permissions,
            'name' => $user['Name'],
            'email' => $user['Email'],
            'login_time' => time(),
            'last_activity' => time(),
        ];

        $_SESSION['authenticated'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        if ($rememberMe) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + $this->authConfig['remember_me_duration'], '/');

            $this->db->update(
                'Users',
                ['RememberToken' => $token, 'UpdatedAt' => date('Y-m-d H:i:s')],
                'Id = ?',
                [(int) $user['Id']]
            );
        }
    }

    public function isAuthenticated()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            return false;
        }

        $lastActivity = $_SESSION['user']['last_activity'] ?? 0;
        $sessionLifetime = (int) ($this->authConfig['session_lifetime'] ?? 7200);

        if (time() - $lastActivity > $sessionLifetime) {
            $this->logout();
            return false;
        }

        $_SESSION['user']['last_activity'] = time();

        return true;
    }

    public function hasPermission($permission)
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $permission = (string) $permission;

        $permissions = $_SESSION['user']['permissions'] ?? [];

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        $permissions = $this->getPermissionsForUser((int) ($_SESSION['user']['id'] ?? 0));
        $_SESSION['user']['permissions'] = $permissions;

        return in_array($permission, $permissions, true);
    }

    public function getCurrentUser()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $_SESSION['user'];
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user']['id'] ?? null;

        if ($userId !== null) {
            $this->db->update('Users', ['RememberToken' => null, 'UpdatedAt' => date('Y-m-d H:i:s')], 'Id = ?', [$userId]);
        }

        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }

        $username = $_SESSION['user']['username'] ?? 'unknown';

        session_unset();
        session_destroy();

        $this->log("Usuario {$username} cerró sesión", 'info');
    }

    public function getCsrfToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';

        return hash_equals($sessionToken, (string) $token);
    }

    private function findUser(string $username): ?array
    {
        $sql = 'SELECT * FROM Users WHERE Username = ? LIMIT 1';

        return $this->db->fetchOne($sql, [$username]);
    }

    private function updateLastLogin(int $userId): void
    {
        $this->db->update('Users', ['LastLoginAt' => date('Y-m-d H:i:s'), 'UpdatedAt' => date('Y-m-d H:i:s')], 'Id = ?', [$userId]);
    }

    private function getUserRoles(int $userId): array
    {
        $sql = 'SELECT r.Name FROM Roles r
                INNER JOIN UserRoles ur ON ur.RoleId = r.Id
                WHERE ur.UserId = ?';

        $rows = $this->db->fetchAll($sql, [$userId]);

        return array_values(array_unique(array_map(fn ($row) => $row['Name'], $rows)));
    }

    private function getPermissionsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = 'SELECT DISTINCT p.Name
                FROM Permissions p
                INNER JOIN RolePermissions rp ON rp.PermissionId = p.Id
                INNER JOIN UserRoles ur ON ur.RoleId = rp.RoleId
                WHERE ur.UserId = ?';

        $rows = $this->db->fetchAll($sql, [$userId]);

        return array_values(array_unique(array_map(fn ($row) => $row['Name'], $rows)));
    }

    private function isLockedOut(string $username): bool
    {
        $maxAttempts = (int) $this->authConfig['max_login_attempts'];
        $lockoutDuration = (int) $this->authConfig['lockout_duration'];

        $sql = 'SELECT OccurredAt FROM LoginAttempts
                WHERE Username = ? AND WasSuccessful = 0
                ORDER BY OccurredAt DESC
                LIMIT ?';

        $rows = $this->db->fetchAll($sql, [$username, $maxAttempts]);

        if (count($rows) < $maxAttempts) {
            return false;
        }

        $latest = $rows[0]['OccurredAt'] ?? null;

        if ($latest === null) {
            return false;
        }

        $lastAttempt = strtotime($latest);

        if ($lastAttempt === false) {
            return false;
        }

        return (time() - $lastAttempt) < $lockoutDuration;
    }

    private function recordLoginAttempt(string $username, bool $success): void
    {
        $this->db->insert('LoginAttempts', [
            'Username' => $username,
            'WasSuccessful' => $success ? 1 : 0,
            'IpAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            'UserAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'OccurredAt' => date('Y-m-d H:i:s'),
        ]);
    }

    private function clearFailedAttempts(string $username): void
    {
        $sql = 'DELETE FROM LoginAttempts WHERE Username = ? AND WasSuccessful = 0';
        $this->db->execute_query($sql, [$username]);
    }

    public function repairLoginAttemptsFile(): bool
    {
        // Ya no se usa almacenamiento en archivo; nada que reparar.
        return false;
    }

    public function changePassword($currentPassword, $newPassword)
    {
        if (!$this->isAuthenticated()) {
            $this->errors['password'][] = 'No estás autenticado';
            return false;
        }

        $user = $this->getCurrentUser();
        $userData = $this->findUser($user['username']);

        if (!$userData || !password_verify($currentPassword, $userData['PasswordHash'])) {
            $this->errors['password'][] = 'Contraseña actual incorrecta';
            return false;
        }

        if (strlen($newPassword) < $this->authConfig['password_min_length']) {
            $this->errors['password'][] = 'La nueva contraseña debe tener al menos ' . $this->authConfig['password_min_length'] . ' caracteres';
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);

        $this->db->update('Users', [
            'PasswordHash' => $hash,
            'UpdatedAt' => date('Y-m-d H:i:s'),
        ], 'Id = ?', [$userData['Id']]);

        $this->log("Usuario {$user['username']} cambió su contraseña", 'info');

        return true;
    }
}
