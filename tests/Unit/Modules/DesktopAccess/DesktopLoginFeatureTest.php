<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\DesktopAccess;

use App\Exceptions\ApiException;
use App\Modules\DesktopAccess\Contracts\IDesktopLoginRepository;
use App\Modules\DesktopAccess\Domain\DesktopLoginPolicy;
use App\Modules\DesktopAccess\Features\LoginDesktop\Command;
use App\Modules\DesktopAccess\Features\LoginDesktop\Handler;
use App\Services\JwtService;
use PHPUnit\Framework\TestCase;

final class DesktopLoginRepositoryFake implements IDesktopLoginRepository
{
    /** @var array{total: int, firstAttemptAt: string|null} */
    public array $attemptSummary = [
        'total' => 0,
        'firstAttemptAt' => null,
    ];

    /** @var array<string, mixed>|null */
    public ?array $administrator = null;

    /** @var list<array<string, mixed>> */
    public array $recordedAttempts = [];

    public function failedAttemptsSince(
        string $loginIdentifier,
        string $codeAccess,
        string $since,
    ): array {
        return $this->attemptSummary;
    }

    public function findAdministrator(string $codeAccess, string $login): ?array
    {
        return $this->administrator;
    }

    public function recordAttempt(array $attempt): void
    {
        $this->recordedAttempts[] = $attempt;
    }
}

final class DesktopLoginFeatureTest extends TestCase
{
    public function test_valid_credentials_return_a_token_and_record_success(): void
    {
        $repository = new DesktopLoginRepositoryFake();
        $repository->administrator = [
            'CustomerId' => 'customer-1',
            'CustomerName' => 'Gimnasio Uno',
            'AdminId' => 'admin-1',
            'Username' => 'owner',
            'Email' => 'owner@example.test',
            'Password' => password_hash('secret', PASSWORD_DEFAULT),
            'Role' => 2,
        ];
        $handler = new Handler(
            $repository,
            new DesktopLoginPolicy(),
            new JwtService('desktop-login-unit-secret'),
        );

        $result = $handler->handle(new Command(
            [
                'codeAccess' => 'GYM-1',
                'login' => 'owner',
                'password' => 'secret',
            ],
            '127.0.0.1',
            'Unit Test',
        ));

        self::assertSame('success', $result->data['status']);
        self::assertNotSame('', $result->data['token']);
        self::assertSame('customer-1', $result->data['customer']['customerId']);
        self::assertTrue($repository->recordedAttempts[0]['wasSuccessful']);
        self::assertNull($repository->recordedAttempts[0]['failureReason']);
    }

    public function test_missing_code_access_preserves_the_legacy_error_body(): void
    {
        $handler = new Handler(
            new DesktopLoginRepositoryFake(),
            new DesktopLoginPolicy(),
            new JwtService('desktop-login-unit-secret'),
        );

        try {
            $handler->handle(new Command([], null, null));
            self::fail('Expected validation to fail.');
        } catch (ApiException $exception) {
            self::assertSame(
                ['error' => 'El campo codeAccess es obligatorio'],
                $exception->toArray(),
            );
        }
    }

    public function test_fifth_recent_failure_blocks_login_before_credentials_are_queried(): void
    {
        $repository = new DesktopLoginRepositoryFake();
        $repository->attemptSummary = [
            'total' => 5,
            'firstAttemptAt' => date('Y-m-d H:i:s', time() - 60),
        ];
        $handler = new Handler(
            $repository,
            new DesktopLoginPolicy(),
            new JwtService('desktop-login-unit-secret'),
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Demasiados intentos fallidos');

        $handler->handle(new Command(
            [
                'codeAccess' => 'GYM-1',
                'login' => 'owner',
                'password' => 'secret',
            ],
            null,
            null,
        ));
    }
}
