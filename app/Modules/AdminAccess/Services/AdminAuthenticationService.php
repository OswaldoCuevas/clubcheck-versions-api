<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Services;

use App\Modules\AdminAccess\Contracts\IAdminAuthRepository;

final readonly class AdminAuthenticationService
{
    /** @param array<string, int|string> $configuration */
    public function __construct(
        private IAdminAuthRepository $users,
        private array $configuration,
    ) {
    }

    public function isAuthenticated(): bool
    {
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            return false;
        }

        $lastActivity = (int) ($_SESSION['user']['last_activity'] ?? 0);
        $sessionLifetime = (int) ($this->configuration['session_lifetime'] ?? 7200);

        if (time() - $lastActivity > $sessionLifetime) {
            $this->logout();

            return false;
        }

        $_SESSION['user']['last_activity'] = time();

        return true;
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    public function validateCsrfToken(mixed $token): bool
    {
        return hash_equals(
            (string) ($_SESSION['csrf_token'] ?? ''),
            (string) $token,
        );
    }

    public function authenticate(
        string $username,
        string $password,
        bool $rememberMe,
        ?string $ipAddress,
        ?string $userAgent,
    ): bool {
        $username = trim($username);

        if ($username === '' || $password === '' || $this->isLockedOut($username)) {
            return false;
        }

        $user = $this->users->findByUsername($username);

        if ($user === null) {
            $this->users->recordAttempt(
                $username,
                false,
                $ipAddress,
                $userAgent,
            );

            return false;
        }

        if (!password_verify($password, (string) $user['PasswordHash'])) {
            $this->users->recordAttempt(
                $username,
                false,
                $ipAddress,
                $userAgent,
            );

            return false;
        }

        if (!(bool) $user['IsActive']) {
            return false;
        }

        $this->users->clearFailedAttempts($username);
        $this->users->recordAttempt(
            $username,
            true,
            $ipAddress,
            $userAgent,
        );
        $this->establishSession($user, $rememberMe);
        $this->users->updateLastLogin((int) $user['Id']);

        if (function_exists('logger')) {
            logger("Usuario {$username} inició sesión", 'info');
        }

        return true;
    }

    public function logout(): void
    {
        $userId = $_SESSION['user']['id'] ?? null;
        $username = $_SESSION['user']['username'] ?? 'unknown';

        if (is_numeric($userId)) {
            $this->users->saveRememberToken((int) $userId, null);
        }

        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }

        session_unset();
        session_destroy();

        if (function_exists('logger')) {
            logger("Usuario {$username} cerró sesión", 'info');
        }
    }

    public function safeRedirectAfterLogin(): string
    {
        $redirect = $_SESSION['redirect_after_login'] ?? '/admin';
        unset($_SESSION['redirect_after_login']);

        if (!is_string($redirect) || $redirect === '') {
            return '/admin';
        }

        if (!str_starts_with($redirect, '/')) {
            return '/admin';
        }

        $path = parse_url($redirect, PHP_URL_PATH);
        $blockedPaths = [
            '/favicon.ico',
            '/favicon.png',
            '/apple-touch-icon.png',
            '/apple-touch-icon-precomposed.png',
            '/login',
            '/logout',
        ];

        if (!is_string($path) || $path === '' || $path[0] !== '/') {
            return '/admin';
        }

        if (
            in_array($path, $blockedPaths, true)
            || str_starts_with($path, '/public/assets/')
            || str_starts_with($path, '/assets/')
        ) {
            return '/admin';
        }

        return $redirect;
    }

    /** @param array<string, mixed> $user */
    private function establishSession(array $user, bool $rememberMe): void
    {
        $userId = (int) $user['Id'];
        $roles = $this->users->roles($userId);

        $_SESSION['user'] = [
            'id' => $userId,
            'username' => $user['Username'],
            'role' => $roles[0] ?? null,
            'roles' => $roles,
            'permissions' => $this->users->permissions($userId),
            'name' => $user['Name'],
            'email' => $user['Email'],
            'login_time' => time(),
            'last_activity' => time(),
        ];
        $_SESSION['authenticated'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        if (!$rememberMe) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $duration = (int) ($this->configuration['remember_me_duration'] ?? 2592000);
        setcookie('remember_token', $token, time() + $duration, '/');
        $this->users->saveRememberToken($userId, $token);
    }

    private function isLockedOut(string $username): bool
    {
        $limit = (int) ($this->configuration['max_login_attempts'] ?? 5);
        $attempts = $this->users->recentFailedAttempts($username, $limit);

        if (count($attempts) < $limit) {
            return false;
        }

        $latest = strtotime((string) ($attempts[0]['OccurredAt'] ?? ''));
        $duration = (int) ($this->configuration['lockout_duration'] ?? 900);

        return $latest !== false && (time() - $latest) < $duration;
    }
}
