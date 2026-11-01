<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\RegisterToken;

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
        $customerId = $command->authenticatedCustomerId
            ?? trim((string) ($command->payload['customerId'] ?? ''));
        $token = trim((string) ($command->payload['token'] ?? ''));
        $deviceName = array_key_exists('deviceName', $command->payload)
            ? trim((string) $command->payload['deviceName'])
            : null;

        if ($customerId === '' || $token === '') {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerValidationFailed,
                ['error' => 'Los campos customerId y token son obligatorios'],
            );
        }

        if ($deviceName === '') {
            $deviceName = null;
        }

        $result = $this->customers->registerToken(
            $customerId,
            $token,
            $deviceName,
        );

        if ($result['status'] === 'not_found') {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerResourceNotFound,
                ['error' => 'Cliente no encontrado'],
            );
        }

        if ($result['status'] === 'not_waiting') {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerConflict,
                [
                    'error' => 'El cliente no está esperando un nuevo token',
                    'waitingForToken' => $result['customer']['waitingForToken'] ?? false,
                ],
            );
        }

        return new JsonResult([
            'status' => 'updated',
            'customer' => $result['customer'],
        ]);
    }
}
