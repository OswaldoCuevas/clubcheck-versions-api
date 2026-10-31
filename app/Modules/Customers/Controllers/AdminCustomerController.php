<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonResponse;
use App\Http\JsonResult;
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
        $this->send($this->mediator->send(new RegenerateAccessKeyCommand($this->jsonBody())));
    }

    public function deleteCustomerJson(string $customerId): void
    {
        $this->send($this->mediator->send(new DeleteCustomerCommand($customerId)));
    }

    public function saveCustomerJson(): void
    {
        $this->send($this->mediator->send(new SaveCustomerCommand($this->jsonBody())));
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        return is_array($payload) ? $payload : [];
    }

    private function send(JsonResult $result): never
    {
        JsonResponse::send($result->data, $result->statusCode);
    }
}
