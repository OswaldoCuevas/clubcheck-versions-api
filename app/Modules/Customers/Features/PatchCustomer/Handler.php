<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\PatchCustomer;

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
        $payload = $command->payload;
        $customerId = $command->authenticatedCustomerId
            ?? trim((string) ($payload['customerId'] ?? ''));

        if ($customerId === '') {
            $this->policy->fail('El campo customerId es obligatorio');
        }

        $attributes = [];

        if (array_key_exists('planCode', $payload)) {
            $planCode = $payload['planCode'] !== null
                ? trim((string) $payload['planCode'])
                : null;

            if ($planCode !== null && mb_strlen($planCode) > 50) {
                $this->policy->fail('El PlanCode debe tener máximo 50 caracteres');
            }

            $attributes['planCode'] = $planCode === '' ? null : $planCode;
        }

        if (array_key_exists('name', $payload)) {
            $name = $payload['name'] !== null
                ? trim((string) $payload['name'])
                : null;

            if ($name === null || $name === '') {
                $this->policy->fail('El nombre no puede estar vacío');
            }

            $attributes['name'] = $name;
        }

        if (array_key_exists('email', $payload)) {
            $email = $payload['email'] !== null
                ? trim((string) $payload['email'])
                : null;

            if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->policy->fail('Formato de correo inválido');
            }

            $attributes['email'] = $email === '' ? null : $email;
        }

        if (array_key_exists('phone', $payload)) {
            $phone = $payload['phone'] !== null
                ? trim((string) $payload['phone'])
                : null;
            $attributes['phone'] = $phone === '' ? null : $phone;
        }

        if ($attributes === []) {
            $this->policy->fail(
                'Debes proporcionar al menos un atributo para actualizar',
            );
        }

        $customer = $this->customers->patch($customerId, $attributes);

        if ($customer === null) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerResourceNotFound,
                ['error' => 'Cliente no encontrado'],
            );
        }

        return new JsonResult([
            'status' => 'updated',
            'customer' => $customer,
        ]);
    }
}
