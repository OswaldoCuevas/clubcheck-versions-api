<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Customers;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Customers\Contracts\IDesktopSyncCoordinator;
use App\Modules\Customers\Features\PullDesktop\Handler as PullDesktopHandler;
use App\Modules\Customers\Features\PullDesktop\Query as PullDesktopQuery;
use App\Modules\Customers\Features\PushDesktop\Command as PushDesktopCommand;
use App\Modules\Customers\Features\PushDesktop\Handler as PushDesktopHandler;
use PHPUnit\Framework\TestCase;

final class DesktopSyncCoordinatorFake implements IDesktopSyncCoordinator
{
    /** @var array<string, mixed>|null */
    public ?array $pullCall = null;

    /** @var array<string, mixed>|null */
    public ?array $pushCall = null;

    /** @var list<string> */
    public array $names = [
        'users',
        'products',
    ];

    public function pull(
        string $customerApiId,
        bool $includeRemoved,
        array $includeRemovedByBulk,
    ): array {
        $this->pullCall = [
            'customerApiId' => $customerApiId,
            'includeRemoved' => $includeRemoved,
            'includeRemovedByBulk' => $includeRemovedByBulk,
        ];

        return [
            'users' => [],
            'products' => [],
        ];
    }

    public function push(string $customerApiId, array $bulks): array
    {
        $this->pushCall = [
            'customerApiId' => $customerApiId,
            'bulks' => $bulks,
        ];

        return [
            'users' => [],
            'products' => [],
        ];
    }

    public function bulkNames(): array
    {
        return $this->names;
    }
}

final class DesktopSyncFeatureTest extends TestCase
{
    public function test_pull_uses_authenticated_customer_and_normalizes_flags(): void
    {
        $coordinator = new DesktopSyncCoordinatorFake();
        $handler = new PullDesktopHandler($coordinator);

        $result = $handler->handle(new PullDesktopQuery(
            [
                'customerApiId' => 'untrusted-customer',
                'includeRemoved' => 'yes',
                'includeRemovedByBulk' => [
                    'products' => 0,
                ],
            ],
            'authenticated-customer',
        ));

        self::assertSame(200, $result->statusCode);
        self::assertSame('authenticated-customer', $result->data['customerApiId']);
        self::assertSame(
            [
                'customerApiId' => 'authenticated-customer',
                'includeRemoved' => true,
                'includeRemovedByBulk' => [
                    'products' => false,
                ],
            ],
            $coordinator->pullCall,
        );
    }

    public function test_pull_rejects_an_invalid_bulk_flag(): void
    {
        $coordinator = new DesktopSyncCoordinatorFake();
        $handler = new PullDesktopHandler($coordinator);

        try {
            $handler->handle(new PullDesktopQuery(
                [
                    'includeRemovedByBulk' => [
                        'users' => 'invalid',
                    ],
                ],
                'customer-1',
            ));
            self::fail('Se esperaba un error controlado de validación.');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->getStatusCode());
            self::assertSame(
                ApiErrorCode::DesktopSyncValidationFailed,
                $exception->getApiErrorCode(),
            );
            self::assertSame(
                [
                    'error' => 'El campo includeRemovedByBulk.users debe ser booleano (true/false, 1/0)',
                ],
                $exception->toArray(),
            );
        }

        self::assertNull($coordinator->pullCall);
    }

    public function test_push_accepts_data_alias_and_uses_authenticated_customer(): void
    {
        $coordinator = new DesktopSyncCoordinatorFake();
        $handler = new PushDesktopHandler($coordinator);
        $bulks = [
            'users' => [[
                'Id' => 'user-1',
            ]],
        ];

        $result = $handler->handle(new PushDesktopCommand(
            ['data' => $bulks],
            'customer-1',
        ));

        self::assertSame(200, $result->statusCode);
        self::assertSame(
            [
                'customerApiId' => 'customer-1',
                'bulks' => $bulks,
            ],
            $coordinator->pushCall,
        );
    }

    public function test_push_rejects_non_array_bulk_before_persistence(): void
    {
        $coordinator = new DesktopSyncCoordinatorFake();
        $handler = new PushDesktopHandler($coordinator);

        try {
            $handler->handle(new PushDesktopCommand(
                [
                    'bulks' => [
                        'products' => 'invalid',
                    ],
                ],
                'customer-1',
            ));
            self::fail('Se esperaba un error controlado de validación.');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->getStatusCode());
            self::assertSame(
                ['error' => 'El bulk products debe ser un arreglo de registros'],
                $exception->toArray(),
            );
        }

        self::assertNull($coordinator->pushCall);
    }
}
