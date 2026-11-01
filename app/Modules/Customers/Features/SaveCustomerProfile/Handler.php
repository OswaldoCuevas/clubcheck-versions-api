<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\SaveCustomerProfile;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerApiRepository;
use App\Modules\Customers\Contracts\ICustomerBillingGateway;
use App\Modules\Customers\Services\CustomerPayloadPolicy;
use RuntimeException;

final readonly class Handler
{
    public function __construct(
        private ICustomerApiRepository $customers,
        private ICustomerBillingGateway $billing,
        private CustomerPayloadPolicy $policy,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        $payload = $command->payload;
        $customerId = $command->authenticatedCustomerId
            ?? trim((string) ($payload['customerId'] ?? ''));
        $existing = $customerId !== ''
            ? $this->customers->find($customerId)
            : null;
        $attributes = $this->attributes($payload);

        if ($attributes === []) {
            $this->policy->fail('No se enviaron atributos');
        }

        if ($existing !== null) {
            return $this->update($customerId, $attributes);
        }

        if (empty($attributes['name'])) {
            $this->policy->fail('El nombre es obligatorio al crear un cliente');
        }

        $privacyAcceptance = $this->policy->acceptance(
            $payload['privacyAcceptance'] ?? null,
            true,
            false,
        );
        $termsAcceptance = $this->policy->acceptance(
            $payload['termsAndConditions'] ?? null,
            true,
            true,
        );

        if (empty($attributes['billingId'])) {
            if (empty($attributes['email'])) {
                $this->policy->fail('El email es obligatorio');
            }

            $billingResult = $this->billing->createCustomer(
                $attributes['name'],
                $attributes['email'],
                $attributes['phone'] ?? null,
            );

            if (empty($billingResult['success'])) {
                throw ApiException::preservingResponse(
                    ApiErrorCode::CustomerOperationFailed,
                    [
                        'error' => 'No se pudo crear el cliente en Stripe: '
                            . ($billingResult['error'] ?? 'Error desconocido'),
                        'code' => 'stripe_customer_creation_failed',
                    ],
                );
            }

            $attributes['billingId'] = $billingResult['customer']->id;
        }

        try {
            $result = $this->customers->register([
                'customerId' => $customerId,
                'name' => $attributes['name'],
                'billingId' => $attributes['billingId'] ?? null,
                'planCode' => $attributes['planCode'] ?? null,
                'email' => $attributes['email'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'deviceName' => $attributes['deviceName'] ?? null,
                'token' => $attributes['token'] ?? null,
                'isActive' => $attributes['isActive'] ?? true,
                'privacyAcceptance' => $privacyAcceptance,
                'termsAndConditionsAcceptance' => $termsAcceptance,
            ]);
        } catch (RuntimeException $exception) {
            $this->throwRepositoryError($exception);
        }

        $response = [
            'status' => $result['found'] ? 'updated' : 'created',
            'customer' => $result['customer'],
        ];

        if (isset($result['accessKey'])) {
            $response['accessKey'] = $result['accessKey'];
        }

        return new JsonResult(
            $response,
            $result['found'] ? 200 : 201,
        );
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function attributes(array $payload): array
    {
        $attributes = [];

        foreach (
            [
                'name',
                'codeAccess',
                'phone',
                'deviceName',
                'billingId',
                'token',
            ] as $attribute
        ) {
            if (array_key_exists($attribute, $payload)) {
                $attributes[$attribute] = $payload[$attribute] !== null
                    ? trim((string) $payload[$attribute])
                    : null;
            }
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

        if (array_key_exists('planCode', $payload)) {
            $planCode = $payload['planCode'] !== null
                ? trim((string) $payload['planCode'])
                : null;

            if ($planCode !== null && mb_strlen($planCode) > 50) {
                $this->policy->fail('El PlanCode debe tener máximo 50 caracteres');
            }

            $attributes['planCode'] = $planCode === '' ? null : $planCode;
        }

        if (array_key_exists('isActive', $payload)) {
            $attributes['isActive'] = (bool) $payload['isActive'];
        }

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function update(string $customerId, array $attributes): JsonResult
    {
        try {
            $customer = $this->customers->save($customerId, $attributes);
        } catch (RuntimeException $exception) {
            $this->throwRepositoryError($exception);
        }

        return new JsonResult([
            'status' => 'updated',
            'customer' => $customer,
        ]);
    }

    private function throwRepositoryError(RuntimeException $exception): never
    {
        $response = match ($exception->getMessage()) {
            'email_already_registered' => [
                'error' => 'El correo ya está registrado para otro cliente',
                'code' => 'email_conflict',
            ],
            'code_access_already_registered' => [
                'error' => 'El CodeAccess ya está registrado para otro cliente',
                'code' => 'code_access_conflict',
            ],
            'access_key_secret_missing' => [
                'error' => 'No se pudo generar la AccessKey. Falta configurar ACCESS_KEY_SECRET.',
                'code' => 'access_key_generation_failed',
            ],
            default => throw $exception,
        };
        $errorCode = $exception->getMessage() === 'access_key_secret_missing'
            ? ApiErrorCode::CustomerOperationFailed
            : ApiErrorCode::CustomerConflict;

        throw ApiException::preservingResponse($errorCode, $response, $exception);
    }
}
