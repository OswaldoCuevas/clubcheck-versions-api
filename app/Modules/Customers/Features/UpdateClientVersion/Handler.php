<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\UpdateClientVersion;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerApiRepository;
use App\Modules\Customers\Services\CustomerPayloadPolicy;

final readonly class Handler
{
    public function __construct(
        private ICustomerApiRepository $customers,
        private CustomerPayloadPolicy $policy,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        $customerId = $command->authenticatedCustomerId;

        if (!$customerId) {
            throw ApiException::preservingResponse(
                ApiErrorCode::Unauthorized,
                ['error' => 'No se pudo obtener el ID del cliente de la sesión'],
            );
        }

        $version = trim((string) ($command->payload['version'] ?? ''));

        if ($version === '') {
            $this->policy->fail('El parámetro version es obligatorio');
        }

        if (mb_strlen($version) > 50) {
            $this->policy->fail('La versión no puede exceder 50 caracteres');
        }

        $customer = $this->customers->updateClientVersion(
            $customerId,
            $version,
        );

        if ($customer === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerResourceNotFound,
                ['error' => 'Cliente no encontrado'],
            );
        }

        return new JsonResult([
            'success' => true,
            'message' => 'Versión del cliente actualizada correctamente',
            'customerId' => $customer['customerId'],
            'clientVersion' => $version,
            'data' => [
                'customerId' => $customer['customerId'],
                'name' => $customer['name'],
                'clientVersion' => $version,
            ],
        ]);
    }
}
