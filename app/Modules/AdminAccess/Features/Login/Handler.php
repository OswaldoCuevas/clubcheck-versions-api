<?php

declare(strict_types=1);

namespace App\Modules\AdminAccess\Features\Login;

use App\Modules\AdminAccess\Domain\LoginPageResult;
use App\Modules\AdminAccess\Services\AdminAuthenticationService;

final readonly class Handler
{
    public function __construct(private AdminAuthenticationService $authentication)
    {
    }

    public function handle(Command $command): LoginPageResult
    {
        $payload = $command->payload;

        if (!$this->authentication->validateCsrfToken($payload['csrf_token'] ?? '')) {
            return new LoginPageResult(
                $this->authentication->csrfToken(),
                'Token de seguridad inválido',
            );
        }

        $username = trim((string) ($payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($username === '' || $password === '') {
            return new LoginPageResult(
                $this->authentication->csrfToken(),
                'Por favor complete todos los campos',
            );
        }

        $authenticated = $this->authentication->authenticate(
            $username,
            $password,
            isset($payload['remember']),
            $command->ipAddress,
            $command->userAgent,
        );

        if (!$authenticated) {
            return new LoginPageResult(
                $this->authentication->csrfToken(),
                'Usuario o contraseña incorrectos',
            );
        }

        return new LoginPageResult(
            '',
            '',
            $this->authentication->safeRedirectAfterLogin(),
        );
    }
}
