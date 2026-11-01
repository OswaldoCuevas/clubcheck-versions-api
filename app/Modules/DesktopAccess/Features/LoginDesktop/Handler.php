<?php

declare(strict_types=1);

namespace App\Modules\DesktopAccess\Features\LoginDesktop;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\DesktopAccess\Contracts\IDesktopLoginRepository;
use App\Modules\DesktopAccess\Domain\DesktopLoginPolicy;
use App\Services\JwtService;

final readonly class Handler
{
    private const JWT_TTL_SECONDS = 1296000;
    private const ATTEMPT_WINDOW_SECONDS = 600;
    private const ATTEMPT_LIMIT = 5;

    public function __construct(
        private IDesktopLoginRepository $logins,
        private DesktopLoginPolicy $policy,
        private JwtService $jwt,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        $payload = $command->payload;
        $codeAccess = trim((string) ($payload['codeAccess'] ?? ''));

        if ($codeAccess === '') {
            $this->validationError('El campo codeAccess es obligatorio');
        }

        $login = trim((string) (
            $payload['login']
                ?? $payload['username']
                ?? $payload['email']
                ?? $payload['administrator']
                ?? ''
        ));

        if ($login === '') {
            $this->validationError('El campo login es obligatorio');
        }

        $password = trim((string) ($payload['password'] ?? ''));

        if ($password === '') {
            $this->validationError('El campo password es obligatorio');
        }

        $normalizedLogin = $this->policy->normalizeIdentifier($login, 160);
        $normalizedCode = $this->policy->normalizeIdentifier($codeAccess, 100);
        $attempts = $this->logins->failedAttemptsSince(
            $normalizedLogin,
            $normalizedCode,
            date('Y-m-d H:i:s', time() - self::ATTEMPT_WINDOW_SECONDS),
        );

        if ($attempts['total'] >= self::ATTEMPT_LIMIT) {
            $firstAttempt = strtotime((string) $attempts['firstAttemptAt']);
            $lockedUntilTimestamp = $firstAttempt + self::ATTEMPT_WINDOW_SECONDS;

            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerTooManyAttempts,
                [
                    'error' => 'Demasiados intentos fallidos. Intenta nuevamente cuando termine la ventana de 10 minutos.',
                    'code' => 'too_many_attempts',
                    'retryAfter' => max(1, $lockedUntilTimestamp - time()),
                    'lockedUntil' => date('Y-m-d H:i:s', $lockedUntilTimestamp),
                ],
            );
        }

        $administrator = $this->logins->findAdministrator($codeAccess, $login);
        $validPassword = $administrator !== null
            && $this->policy->passwordMatches(
                $password,
                (string) ($administrator['Password'] ?? ''),
            );

        $this->recordAttempt(
            $normalizedLogin,
            $normalizedCode,
            $administrator,
            $command,
            $validPassword,
        );

        if (!$validPassword) {
            throw ApiException::preservingResponse(
                ApiErrorCode::InvalidCredentials,
                ['error' => 'Credenciales invalidas'],
            );
        }

        $token = $this->jwt->createToken([
            'name' => $administrator['CustomerName'],
            'customerId' => $administrator['CustomerId'],
            'adminId' => $administrator['AdminId'],
            'role' => (int) $administrator['Role'],
        ], self::JWT_TTL_SECONDS);

        return new JsonResult([
            'status' => 'success',
            'token' => $token,
            'expiresIn' => self::JWT_TTL_SECONDS,
            'expiresAt' => date(
                'Y-m-d H:i:s',
                time() + self::JWT_TTL_SECONDS,
            ),
            'customer' => [
                'name' => $administrator['CustomerName'],
                'customerId' => $administrator['CustomerId'],
            ],
            'administrator' => [
                'id' => $administrator['AdminId'],
                'username' => $administrator['Username'],
                'email' => $administrator['Email'],
                'role' => (int) $administrator['Role'],
            ],
        ]);
    }

    private function recordAttempt(
        string $login,
        string $codeAccess,
        ?array $administrator,
        Command $command,
        bool $successful,
    ): void {
        $this->logins->recordAttempt([
            'loginIdentifier' => $login,
            'codeAccess' => $codeAccess,
            'customerId' => $administrator['CustomerId'] ?? null,
            'adminId' => $administrator['AdminId'] ?? null,
            'ipAddress' => $this->policy->nullableString($command->ipAddress, 45),
            'userAgent' => $this->policy->nullableString($command->userAgent, 500),
            'wasSuccessful' => $successful,
            'failureReason' => $successful
                ? null
                : ($administrator !== null ? 'invalid_password' : 'invalid_login'),
            'createdAt' => date('Y-m-d H:i:s'),
        ]);
    }

    private function validationError(string $message): never
    {
        throw ApiException::preservingResponse(
            ApiErrorCode::CustomerValidationFailed,
            ['error' => $message],
        );
    }
}
