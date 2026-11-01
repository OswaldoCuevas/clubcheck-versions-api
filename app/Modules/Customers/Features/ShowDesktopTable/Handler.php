<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ShowDesktopTable;

use App\Application\Contracts\ICurrentUserProvider;
use App\Modules\Customers\Contracts\ICustomerRepository;
use App\Modules\Customers\Contracts\IDesktopSyncRepository;
use App\Modules\Customers\Domain\DesktopTableCatalog;
use App\Modules\Customers\Domain\DesktopTablePageResult;

final readonly class Handler
{
    public function __construct(
        private ICurrentUserProvider $currentUser,
        private ICustomerRepository $customers,
        private IDesktopSyncRepository $desktopData,
        private DesktopTableCatalog $tables,
    ) {
    }

    public function handle(Query $query): DesktopTablePageResult
    {
        $table = $this->tables->find($query->tableKey);

        if ($table === null) {
            return new DesktopTablePageResult(
                redirectTo: '/admin/desktop-tables',
            );
        }

        $customers = $this->customers->getAll();
        $records = [];
        $selectedCustomer = null;

        if ($query->customerApiId !== '') {
            $records = $this->desktopData->pullForAdmin(
                $table->bulkName,
                $query->customerApiId,
                true,
            );

            foreach ($customers as $customer) {
                if ($customer['customerId'] === $query->customerApiId) {
                    $selectedCustomer = $customer;
                    break;
                }
            }
        }

        return new DesktopTablePageResult([
            'currentUser' => $this->currentUser->current(),
            'title' => $table->name . ' - Tablas Desktop',
            'isAuthenticated' => true,
            'tableKey' => $query->tableKey,
            'tableInfo' => $table->publicData(),
            'customers' => $customers,
            'customerApiId' => $query->customerApiId,
            'selectedCustomer' => $selectedCustomer,
            'records' => $records,
        ]);
    }
}
