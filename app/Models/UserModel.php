<?php

namespace Models;

// Incluir la clase base Model
require_once __DIR__ . '/../Core/Model.php';

use Core\Model;

class UserModel extends Model
{
    private $users;
    private $authConfig;
    private $permissions;
    private $loginAttemptsFile;

    protected function initialize()
    {
        $userConfig = require __DIR__ . '/../../config/users.php';
        $this->users = $userConfig['users'];
        $this->authConfig = $userConfig['auth'];
        $this->permissions = $userConfig['permissions'];
        $this->loginAttemptsFile = __DIR__ . '/../../storage/login_attempts.json';
    }

    /**
     * Autenticar usuario
     */
    public function authenticate($username, $password, $rememberMe = false)
    {
        // Verificar intentos de login
        if ($this->isLockedOut($username)) {
            $this->errors['login'][] = 'Cuenta bloqueada por intentos fallidos. Intenta más tarde.';
            return false;
        }

        // Buscar usuario
        $user = $this->findUser($username);
        
        if (!$user) {
            $this->recordFailedAttempt($username);
            $this->errors['login'][] = 'Credenciales incorrectas';
            return false;
        }

        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            $this->recordFailedAttempt($username);
            $this->errors['login'][] = 'Credenciales incorrectas';
            return false;
        }

        // Verificar si el usuario está activo
        if (!$user['active']) {
            $this->errors['login'][] = 'Cuenta desactivada';
            return false;
        }

        // Login exitoso
        $this->clearFailedAttempts($username);
        $this->createSession($user, $rememberMe);
        
        $this->log("Usuario {$username} inició sesión", 'info');
        
        return true;
    }

    /**
     * Crear sesión de usuario
     */
    private function createSession($user, $rememberMe = false)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user'] = [
            'username' => $user['username'],
            'role' => $user['role'],
            'name' => $user['name'],
            'email' => $user['email'],
            'login_time' => time(),
            'last_activity' => time(),
        ];

        $_SESSION['authenticated'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Remember me cookie
        if ($rememberMe) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + $this->authConfig['remember_me_duration'], '/');
            
            // En producción, guardar este token en base de datos asociado al usuario
            $_SESSION['remember_token'] = $token;
        }
    }

    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            return false;
        }

        // Verificar timeout de sesión
        $lastActivity = $_SESSION['user']['last_activity'] ?? 0;
        $sessionLifetime = 7200; // 2 horas por defecto
        
        if (time() - $lastActivity > $sessionLifetime) {
            $this->logout();
            return false;
        }

        // Actualizar última actividad
        $_SESSION['user']['last_activity'] = time();
        
        return true;
    }

    /**
     * Verificar permisos del usuario
     */
    public function hasPermission($permission)
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $userRole = $_SESSION['user']['role'] ?? '';
        $userConfig = require __DIR__ . '/../../config/users.php';
        $permissions = $userConfig['permissions'][$userRole] ?? [];

        return in_array($permission, $permissions);
    }

    /**
     * Obtener usuario actual
     */
    public function getCurrentUser()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $_SESSION['user'];
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = $_SESSION['user']['username'] ?? 'unknown';
        
        // Limpiar cookies
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }

        // Destruir sesión
        session_unset();
        session_destroy();

        $this->log("Usuario {$username} cerró sesión", 'info');
    }

    /**
     * Generar token CSRF
     */
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

    /**
     * Validar token CSRF
     */
    public function validateCsrfToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return hash_equals($sessionToken, $token);
    }

    /**
     * Buscar usuario por username
     */
    private function findUser($username)
    {
        return $this->users[$username] ?? null;
    }

    /**
     * Verificar si está bloqueado por intentos fallidos
     */
    private function isLockedOut($username)
    {
        $attempts = $this->getLoginAttempts();
        $userAttempts = $attempts[$username] ?? [];

        if (empty($userAttempts)) {
            return false;
        }

        $lastAttempt = end($userAttempts);
        $lockoutDuration = $this->authConfig['lockout_duration'];
        
        if (count($userAttempts) >= $this->authConfig['max_login_attempts']) {
            return (time() - $lastAttempt) < $lockoutDuration;
        }

        return false;
    }

    /**
     * Registrar intento fallido
     */
    private function recordFailedAttempt($username)
    {
        $attempts = $this->getLoginAttempts();
        
        if (!isset($attempts[$username])) {
            $attempts[$username] = [];
        }

        $attempts[$username][] = time();

        // Mantener solo los últimos intentos
        $maxAttempts = $this->authConfig['max_login_attempts'];
        if (count($attempts[$username]) > $maxAttempts) {
            $attempts[$username] = array_slice($attempts[$username], -$maxAttempts);
        }

        $this->saveLoginAttempts($attempts);
    }

    /**
     * Limpiar intentos fallidos
     */
    private function clearFailedAttempts($username)
    {
        $attempts = $this->getLoginAttempts();
        unset($attempts[$username]);
        $this->saveLoginAttempts($attempts);
    }

    /**
     * Obtener intentos de login
     */
    private function getLoginAttempts()
    {
        if (!file_exists($this->loginAttemptsFile)) {
            return [];
        }

        $data = $this->loadJsonFile($this->loginAttemptsFile);
        return $data ?? [];
    }

    /**
     * Guardar intentos de login
     */
    private function saveLoginAttempts($attempts)
    {
        $this->saveJsonFile($this->loginAttemptsFile, $attempts);
    }

    /**
     * Cambiar contraseña (para futuras implementaciones)
     */
    public function changePassword($currentPassword, $newPassword)
    {
        if (!$this->isAuthenticated()) {
            $this->errors['password'][] = 'No estás autenticado';
            return false;
        }

        $user = $this->getCurrentUser();
        $userData = $this->findUser($user['username']);

        if (!password_verify($currentPassword, $userData['password'])) {
            $this->errors['password'][] = 'Contraseña actual incorrecta';
            return false;
        }

        if (strlen($newPassword) < $this->authConfig['password_min_length']) {
            $this->errors['password'][] = 'La nueva contraseña debe tener al menos ' . $this->authConfig['password_min_length'] . ' caracteres';
            return false;
        }

        // En producción, actualizar en base de datos
        // Por ahora solo logueamos el cambio
        $this->log("Usuario {$user['username']} cambió su contraseña", 'info');
        
        return true;
    }
}
