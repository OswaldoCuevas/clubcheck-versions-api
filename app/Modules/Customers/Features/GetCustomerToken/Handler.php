<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\GetCustomerToken;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerApiRepository;

final readonly class Handler
{
    public function __construct(private ICustomerApiRepository $customers)
    {
    }

    public function handle(Query $query): JsonResult
    {
        $customerId = trim((string) $query->customerId);

        if ($customerId === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerValidationFailed,
                ['error' => 'El campo customerId es obligatorio'],
            );
        }

        $customer = $this->customers->find($customerId);

        if ($customer === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerResourceNotFound,
                ['error' => 'Cliente no encontrado'],
            );
        }

        return new JsonResult([
            'customerId' => $customer['customerId'],
            'name' => $customer['name'],
            'billingId' => $customer['billingId'] ?? null,
            'planCode' => $customer['planCode'] ?? null,
            'token' => $customer['token'] ?? null,
            'waitingForToken' => $customer['waitingForToken'] ?? false,
            'isActive' => $customer['isActive'] ?? true,
            'tokenUpdatedAt' => $customer['tokenUpdatedAt'] ?? null,
            'waitingSince' => $customer['waitingSince'] ?? null,
        ]);
    }
}
