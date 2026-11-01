<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\ViewResponse;
use App\Modules\Customers\Features\DeleteCustomer\Command as DeleteCustomerCommand;
use App\Modules\Customers\Features\ListCustomers\Query as ListCustomersQuery;
use App\Modules\Customers\Features\RegenerateAccessKey\Command as RegenerateAccessKeyCommand;
use App\Modules\Customers\Features\SaveCustomer\Command as SaveCustomerCommand;
use App\Modules\Customers\Features\ShowCustomers\Query as ShowCustomersQuery;

final readonly class AdminCustomerController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function customers(): void
    {
        ViewResponse::render('admin/customers', $this->mediator->send(new ShowCustomersQuery()));
    }

    public function customersJson(): void
    {
        $customers = $this->mediator->send(new ListCustomersQuery());
        JsonResponse::send([
            'count' => count($customers),
            'customers' => $customers,
        ]);
    }

    public function regenerateAccessKey(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new RegenerateAccessKeyCommand(JsonPayload::parse()),
            ),
        );
    }

    public function deleteCustomerJson(string $customerId): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new DeleteCustomerCommand($customerId)),
        );
    }

    public function saveCustomerJson(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new SaveCustomerCommand(JsonPayload::parse()),
            ),
        );
    }
}
