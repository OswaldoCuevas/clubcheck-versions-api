<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ShowDesktopTables;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\Customers\Contracts\ICustomerRepository;
use App\Modules\Customers\Domain\DesktopTableCatalog;
use App\Modules\Customers\Domain\DesktopTablePageResult;

final readonly class Handler
{
    public function __construct(
        private ICurrentUserProvider $currentUser,
        private ICustomerRepository $customers,
        private DesktopTableCatalog $tables,
    ) {
    }

    public function handle(Query $query): DesktopTablePageResult
    {
        return new DesktopTablePageResult([
            'currentUser' => $this->currentUser->current(),
            'title' => 'Tablas Desktop - ClubCheck',
            'isAuthenticated' => true,
            'desktopTables' => $this->tables->all(),
            'customers' => $this->customers->getAll(),
        ]);
    }
}
