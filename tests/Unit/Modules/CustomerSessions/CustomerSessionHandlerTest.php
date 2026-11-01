<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\CustomerSessions;

use App\Exceptions\ApiException;
use App\Modules\CustomerSessions\Contracts\ICustomerSessionRepository;
use App\Modules\CustomerSessions\Features\EndSession\Command as EndSessionCommand;
use App\Modules\CustomerSessions\Features\EndSession\Handler as EndSessionHandler;
use App\Modules\CustomerSessions\Features\HeartbeatSession\Command as HeartbeatSessionCommand;
use App\Modules\CustomerSessions\Features\HeartbeatSession\Handler as HeartbeatSessionHandler;
use App\Modules\CustomerSessions\Features\StartSession\Command as StartSessionCommand;
use App\Modules\CustomerSessions\Features\StartSession\Handler as StartSessionHandler;
use App\Modules\CustomerSessions\Services\CustomerSessionConfiguration;
use App\Modules\CustomerSessions\Services\CustomerSessionMetadataPolicy;
use PHPUnit\Framework\TestCase;

final class CustomerSessionRepositoryFake implements ICustomerSessionRepository
{
    /** @var array<string, mixed>|null */
    public ?array $activeSession = null;

    /** @var array<string, mixed> */
    public array $startedSession = [];

    /** @var array<string, mixed>|null */
    public ?array $heartbeatSession = null;

    public bool $ended = true;
    public int $purgedWith = 0;
    public int $starts = 0;

    public function purgeExpired(int $graceSeconds): void
    {
        $this->purgedWith = $graceSeconds;
    }

    public function findActive(
        string $customerId,
        ?string $deviceId,
        int $graceSeconds,
    ): ?array
    {
        return $this->activeSession;
    }

    public function start(array $sessionData, int $graceSeconds): array
    {
        $this->starts++;

        return $this->startedSession;
    }

    public function heartbeat(
        string $sessionId,
        array $updates,
        int $graceSeconds,
    ): ?array
    {
        return $this->heartbeatSession;
    }

    public function end(string $sessionId, string $reason): bool
    {
        return $this->ended;
    }

    public function list(?string $customerId, int $graceSeconds): array
    {
        return [];
    }
}

final class CustomerSessionHandlerTest extends TestCase
{
    private CustomerSessionConfiguration $configuration;
    private CustomerSessionMetadataPolicy $metadataPolicy;

    protected function setUp(): void
    {
        $this->configuration = new CustomerSessionConfiguration(60, 180, 2048);
        $this->metadataPolicy = new CustomerSessionMetadataPolicy(
            $this->configuration,
        );
    }

    public function test_start_reuses_an_active_session_without_creating_another_one(): void
    {
        $repository = new CustomerSessionRepositoryFake();
        $repository->activeSession = [
            'sessionId' => 'session-1',
            'customerId' => 'customer-1',
            'deviceId' => 'device-1',
            'status' => 'active',
            'startedAt' => 100,
            'lastSeen' => 200,
        ];
        $handler = new StartSessionHandler(
            $repository,
            $this->configuration,
            $this->metadataPolicy,
        );

        $result = $handler->handle(
            new StartSessionCommand(
                [
                    'customerId' => 'customer-1',
                    'deviceId' => 'device-1',
                ],
                '127.0.0.1',
            ),
        );

        self::assertSame(200, $result->statusCode);
        self::assertTrue($result->data['reused']);
        self::assertSame(380, $result->data['expiresAt']);
        self::assertSame(180, $repository->purgedWith);
        self::assertSame(0, $repository->starts);
    }

    public function test_start_rejects_metadata_larger_than_the_configured_limit(): void
    {
        $configuration = new CustomerSessionConfiguration(60, 180, 10);
        $handler = new StartSessionHandler(
            new CustomerSessionRepositoryFake(),
            $configuration,
            new CustomerSessionMetadataPolicy($configuration),
        );

        try {
            $handler->handle(
                new StartSessionCommand(
                    [
                        'customerId' => 'customer-1',
                        'metadata' => ['value' => str_repeat('x', 20)],
                    ],
                    null,
                ),
            );
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertSame(413, $exception->getStatusCode());
            self::assertSame(
                'CUSTOMER_SESSION_METADATA_TOO_LARGE',
                $exception->getErrorCode(),
            );
        }
    }

    public function test_heartbeat_returns_not_found_when_the_repository_has_no_session(): void
    {
        $handler = new HeartbeatSessionHandler(
            new CustomerSessionRepositoryFake(),
            $this->configuration,
            $this->metadataPolicy,
        );

        try {
            $handler->handle(
                new HeartbeatSessionCommand(
                    ['sessionId' => 'missing-session'],
                    '127.0.0.1',
                ),
            );
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame(
                ['error' => 'Sesión no encontrada o expirada'],
                $exception->toArray(),
            );
        }
    }

    public function test_end_preserves_the_not_found_response(): void
    {
        $repository = new CustomerSessionRepositoryFake();
        $repository->ended = false;
        $handler = new EndSessionHandler($repository);

        try {
            $handler->handle(
                new EndSessionCommand(['sessionId' => 'missing-session']),
            );
            self::fail('Se esperaba ApiException.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame(
                ['error' => 'Sesión no encontrada'],
                $exception->toArray(),
            );
        }
    }
}
