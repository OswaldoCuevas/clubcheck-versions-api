<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\AwaitToken;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerApiRepository;

final readonly class Handler
{
    public function __construct(private ICustomerApiRepository $customers)
    {
    }

    public function handle(Command $command): JsonResult
    {
        $customerId = trim((string) ($command->payload['customerId'] ?? ''));

        if ($customerId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerValidationFailed,
                ['error' => 'El campo customerId es obligatorio'],
            );
        }

        $waiting = array_key_exists('waiting', $command->payload)
            ? (bool) $command->payload['waiting']
            : true;
        $customer = $this->customers->setWaitingForToken(
            $customerId,
            $waiting,
        );

        if ($customer === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerResourceNotFound,
                ['error' => 'Cliente no encontrado'],
            );
        }

        return new JsonResult([
            'customerId' => $customer['customerId'],
            'billingId' => $customer['billingId'] ?? null,
            'planCode' => $customer['planCode'] ?? null,
            'waitingForToken' => $customer['waitingForToken'],
            'waitingSince' => $customer['waitingSince'],
        ]);
    }
}
