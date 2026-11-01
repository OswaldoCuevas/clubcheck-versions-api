<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\RegisterCustomer;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerApiRepository;
use App\Modules\Customers\Contracts\ICustomerBillingGateway;
use App\Modules\Customers\Contracts\ICustomerJwtService;
use App\Modules\Customers\Contracts\IFreeCustomerLicenseIssuer;
use App\Modules\Customers\Services\CustomerPayloadPolicy;
use RuntimeException;

final readonly class Handler
{
    public function __construct(
        private ICustomerApiRepository $customers,
        private ICustomerBillingGateway $billing,
        private ICustomerJwtService $jwt,
        private IFreeCustomerLicenseIssuer $licenses,
        private CustomerPayloadPolicy $policy,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        $payload = $command->payload;
        $customerId = trim((string) ($payload['customerId'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $token = trim((string) ($payload['token'] ?? ''));
        $planCode = $this->nullableString($payload, 'planCode');
        $billingId = $this->nullableString($payload, 'billingId');
        $phone = $this->nullableString($payload, 'phone');
        $deviceName = $this->nullableString($payload, 'deviceName');

        if ($name === '' || $token === '') {
            $this->policy->fail('El nombre y el token son obligatorios');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->policy->fail('Formato de correo inválido');
        }

        if ($planCode !== null && mb_strlen($planCode) > 50) {
            $this->policy->fail('El PlanCode debe tener máximo 50 caracteres');
        }

        $privacyAcceptance = $this->policy->acceptance(
            $payload['privacyAcceptance'] ?? null,
            true,
            false,
        );
        $termsAcceptance = $this->policy->acceptance(
            $payload['termsAndConditionsAcceptance'] ?? null,
            true,
            true,
        );
        $existingCustomer = $customerId !== ''
            ? $this->customers->find($customerId)
            : null;

        if ($existingCustomer === null && $billingId === null) {
            if ($email === '') {
                $this->policy->fail(
                    'El email es obligatorio para crear un cliente en Stripe',
                );
            }

            $billingResult = $this->billing->createCustomer(
                $name,
                $email,
                $phone,
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

            $billingId = $billingResult['customer']->id;
        } elseif ($existingCustomer !== null && $billingId === null) {
            $billingId = $existingCustomer['billingId'] ?? null;
        }

        try {
            $result = $this->customers->register([
                'customerId' => $customerId,
                'name' => $name,
                'billingId' => $billingId,
                'planCode' => $planCode,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone,
                'deviceName' => $deviceName,
                'token' => $token,
                'privacyAcceptance' => $privacyAcceptance,
                'termsAndConditionsAcceptance' => $termsAcceptance,
            ]);
        } catch (RuntimeException $exception) {
            $this->throwRepositoryError($exception);
        }

        if ($result['found'] === true) {
            return new JsonResult([
                'found' => true,
                'registered' => false,
                'customer' => $result['customer'],
            ]);
        }

        $registeredCustomerId = $result['customer']['customerId'];
        $jwt = $this->jwt->create($registeredCustomerId, $token);
        $response = [
            'found' => false,
            'registered' => true,
            'customer' => $result['customer'],
            'accessKey' => $result['accessKey'] ?? null,
            'apiToken' => $jwt,
        ];
        $license = $this->licenses->issue(
            $billingId ?? '',
            $name,
            $email !== '' ? $email : null,
            $token,
            $registeredCustomerId,
        );

        return new JsonResult(array_merge($response, $license), 201);
    }

    /** @param array<string, mixed> $payload */
    private function nullableString(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload)) {
            return null;
        }

        $value = trim((string) $payload[$key]);

        return $value !== '' ? $value : null;
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
