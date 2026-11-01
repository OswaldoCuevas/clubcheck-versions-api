<?php

declare(strict_types=1);

namespace App\Modules\Customers\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Middleware\CustomerJwtMiddleware;
use App\Modules\Customers\Features\AwaitToken\Command as AwaitTokenCommand;
use App\Modules\Customers\Features\GetCustomer\Query as GetCustomerQuery;
use App\Modules\Customers\Features\GetCustomerToken\Query as GetCustomerTokenQuery;
use App\Modules\Customers\Features\GetMonthlyMessageCount\Query as GetMonthlyMessageCountQuery;
use App\Modules\Customers\Features\LoginCustomer\Command as LoginCustomerCommand;
use App\Modules\Customers\Features\PatchCustomer\Command as PatchCustomerCommand;
use App\Modules\Customers\Features\RegisterCustomer\Command as RegisterCustomerCommand;
use App\Modules\Customers\Features\RegisterToken\Command as RegisterTokenCommand;
use App\Modules\Customers\Features\SaveCustomerProfile\Command as SaveCustomerCommand;
use App\Modules\Customers\Features\UpdateClientVersion\Command as UpdateClientVersionCommand;
use App\Modules\Customers\Features\ValidateCustomer\Command as ValidateCustomerCommand;
use App\Modules\Customers\Features\ValidateCustomerJwt\Command as ValidateCustomerJwtCommand;

final readonly class CustomerController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function getCustomer(?string $customerId = null): void
    {
        $requestedCustomerId = $customerId
            ?? Request::query('customerId');
        $query = new GetCustomerQuery(
            $requestedCustomerId,
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function saveCustomer(): void
    {
        $command = new SaveCustomerCommand(
            JsonPayload::parse(),
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }

    public function registerCustomer(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new RegisterCustomerCommand(JsonPayload::parse()),
            ),
        );
    }

    public function loginCustomer(): void
    {
        $command = new LoginCustomerCommand(
            JsonPayload::parse(),
            Request::ipAddress(),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }

    public function patchCustomer(): void
    {
        $command = new PatchCustomerCommand(
            JsonPayload::parse(),
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }

    public function validateCustomer(): void
    {
        $command = new ValidateCustomerCommand(
            JsonPayload::parse(),
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }

    public function awaitToken(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new AwaitTokenCommand(JsonPayload::parse()),
            ),
        );
    }

    public function customerToken(): void
    {
        $customerId = Request::query('customerId');
        $customerId = $customerId !== null ? trim($customerId) : null;

        JsonResponse::sendResult(
            $this->mediator->send(
                new GetCustomerTokenQuery($customerId),
            ),
        );
    }

    public function registerToken(): void
    {
        $command = new RegisterTokenCommand(
            JsonPayload::parse(),
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }

    public function getMessagesSendsAtMonth(?string $customerId = null): void
    {
        $requestedCustomerId = $customerId
            ?? Request::query('customerId');
        $query = new GetMonthlyMessageCountQuery(
            $requestedCustomerId,
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        JsonResponse::sendResult($this->mediator->send($query));
    }

    public function validateJwtToken(): void
    {
        $command = new ValidateCustomerJwtCommand(
            JsonPayload::parse(),
            Request::authorizationHeader(),
            Request::query('token'),
            Request::ipAddress(),
            Request::server('HTTP_USER_AGENT'),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }

    public function updateClientVersion(): void
    {
        $command = new UpdateClientVersionCommand(
            JsonPayload::parse(),
            CustomerJwtMiddleware::getCurrentCustomerId(),
        );

        JsonResponse::sendResult($this->mediator->send($command));
    }
}
