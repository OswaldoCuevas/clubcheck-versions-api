<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\AdminAccess;

use App\Modules\AdminAccess\Contracts\IAdminAuthRepository;
use App\Modules\AdminAccess\Services\AdminAuthenticationService;
use PHPUnit\Framework\TestCase;

final class AdminAuthRepositoryFake implements IAdminAuthRepository
{
    /** @var array<string, mixed>|null */
    public ?array $user = null;

    /** @var list<array<string, mixed>> */
    public array $failedAttempts = [];

    /** @var list<array{username: string, successful: bool}> */
    public array $recordedAttempts = [];

    public bool $cleared = false;
    public bool $lastLoginUpdated = false;

    public function findByUsername(string $username): ?array
    {
        return $this->user;
    }

    public function recentFailedAttempts(string $username, int $limit): array
    {
        return $this->failedAttempts;
    }

    public function recordAttempt(
        string $username,
        bool $successful,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $this->recordedAttempts[] = [
            'username' => $username,
            'successful' => $successful,
        ];
    }

    public function clearFailedAttempts(string $username): void
    {
        $this->cleared = true;
    }

    public function roles(int $userId): array
    {
        return ['administrator'];
    }

    public function permissions(int $userId): array
    {
        return ['admin_access'];
    }

    public function updateLastLogin(int $userId): void
    {
        $this->lastLoginUpdated = true;
    }

    public function saveRememberToken(int $userId, ?string $token): void
    {
    }
}

final class AdminAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function test_valid_credentials_create_the_admin_session(): void
    {
        $repository = new AdminAuthRepositoryFake();
        $repository->user = [
            'Id' => 7,
            'Username' => 'admin',
            'PasswordHash' => password_hash('secret', PASSWORD_BCRYPT),
            'IsActive' => 1,
            'Name' => 'Administrator',
            'Email' => 'admin@example.test',
        ];
        $authentication = new AdminAuthenticationService(
            $repository,
            $this->configuration(),
        );

        $authenticated = $authentication->authenticate(
            'admin',
            'secret',
            false,
            '127.0.0.1',
            'Unit Test',
        );

        self::assertTrue($authenticated);
        self::assertTrue($_SESSION['authenticated']);
        self::assertSame(7, $_SESSION['user']['id']);
        self::assertSame(['admin_access'], $_SESSION['user']['permissions']);
        self::assertTrue($repository->cleared);
        self::assertTrue($repository->lastLoginUpdated);
        self::assertTrue($repository->recordedAttempts[0]['successful']);
    }

    public function test_recent_failures_block_authentication_before_user_lookup(): void
    {
        $repository = new AdminAuthRepositoryFake();
        $repository->failedAttempts = array_fill(0, 5, [
            'OccurredAt' => date('Y-m-d H:i:s'),
        ]);
        $authentication = new AdminAuthenticationService(
            $repository,
            $this->configuration(),
        );

        $authenticated = $authentication->authenticate(
            'admin',
            'secret',
            false,
            null,
            null,
        );

        self::assertFalse($authenticated);
        self::assertSame([], $repository->recordedAttempts);
        self::assertArrayNotHasKey('authenticated', $_SESSION);
    }

    public function test_redirect_rejects_external_and_asset_destinations(): void
    {
        $authentication = new AdminAuthenticationService(
            new AdminAuthRepositoryFake(),
            $this->configuration(),
        );

        $_SESSION['redirect_after_login'] = 'https://evil.example.test/path';
        self::assertSame('/admin', $authentication->safeRedirectAfterLogin());

        $_SESSION['redirect_after_login'] = '/assets/application.css';
        self::assertSame('/admin', $authentication->safeRedirectAfterLogin());

        $_SESSION['redirect_after_login'] = '/admin/customers';
        self::assertSame(
            '/admin/customers',
            $authentication->safeRedirectAfterLogin(),
        );
    }

    /** @return array<string, int|string> */
    private function configuration(): array
    {
        return [
            'remember_me_duration' => 2592000,
            'max_login_attempts' => 5,
            'lockout_duration' => 900,
            'session_lifetime' => 7200,
        ];
    }
}
