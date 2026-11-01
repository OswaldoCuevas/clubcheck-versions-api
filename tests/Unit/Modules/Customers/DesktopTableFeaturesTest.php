<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Customers;

use App\Application\Contracts\ICurrentUserProvider;
use App\Exceptions\ApiException;
use App\Modules\Customers\Contracts\ICustomerRepository;
use App\Modules\Customers\Contracts\IDesktopSyncRepository;
use App\Modules\Customers\Domain\DesktopTableCatalog;
use App\Modules\Customers\Features\ListDesktopTableData\Handler as ListHandler;
use App\Modules\Customers\Features\ListDesktopTableData\Query as ListQuery;
use App\Modules\Customers\Features\ShowDesktopTable\Handler as ShowHandler;
use App\Modules\Customers\Features\ShowDesktopTable\Query as ShowQuery;
use PHPUnit\Framework\TestCase;

final class DesktopTableFeaturesTest extends TestCase
{
    public function test_catalog_exposes_every_legacy_desktop_table_without_models(): void
    {
        $tables = (new DesktopTableCatalog())->all();

        self::assertCount(35, $tables);
        self::assertSame('UsersDesktop', $tables['users']['table']);
        self::assertSame('Usuarios', $tables['users']['name']);
        self::assertSame(
            'Configuración de App',
            $tables['app-settings']['name'],
        );
        self::assertArrayNotHasKey('model', $tables['users']);
    }

    public function test_show_table_uses_raw_admin_pull_and_selects_customer(): void
    {
        $currentUser = $this->createStub(ICurrentUserProvider::class);
        $currentUser->method('current')->willReturn(['username' => 'admin']);
        $customers = $this->createStub(ICustomerRepository::class);
        $customers->method('getAll')->willReturn([[
            'customerId' => 'customer-1',
            'name' => 'Customer One',
        ]]);
        $desktopData = $this->createMock(IDesktopSyncRepository::class);
        $desktopData
            ->expects(self::once())
            ->method('pullForAdmin')
            ->with('users', 'customer-1', true)
            ->willReturn([['Id' => 'user-1']]);
        $handler = new ShowHandler(
            $currentUser,
            $customers,
            $desktopData,
            new DesktopTableCatalog(),
        );

        $result = $handler->handle(new ShowQuery('users', 'customer-1'));

        self::assertSame('Usuarios', $result->data['tableInfo']['name']);
        self::assertSame(
            'customer-1',
            $result->data['selectedCustomer']['customerId'],
        );
        self::assertSame([['Id' => 'user-1']], $result->data['records']);
    }

    public function test_unknown_table_redirects_without_querying_persistence(): void
    {
        $handler = new ShowHandler(
            $this->createStub(ICurrentUserProvider::class),
            $this->createStub(ICustomerRepository::class),
            $this->createStub(IDesktopSyncRepository::class),
            new DesktopTableCatalog(),
        );

        $result = $handler->handle(new ShowQuery('unknown', 'customer-1'));

        self::assertSame('/admin/desktop-tables', $result->redirectTo);
    }

    public function test_data_requires_a_known_table_and_customer(): void
    {
        $handler = new ListHandler(
            $this->createStub(IDesktopSyncRepository::class),
            new DesktopTableCatalog(),
        );

        try {
            $handler->handle(new ListQuery('unknown', 'customer-1'));
            self::fail('Expected unknown table to fail.');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertSame(
                ['error' => 'Tabla no encontrada'],
                $exception->toArray(),
            );
        }

        try {
            $handler->handle(new ListQuery('users', ''));
            self::fail('Expected missing customer to fail.');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->getStatusCode());
            self::assertSame(
                ['error' => 'customerApiId es requerido'],
                $exception->toArray(),
            );
        }
    }
}
