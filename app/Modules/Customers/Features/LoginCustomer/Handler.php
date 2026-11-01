<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\LoginCustomer;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerApiRepository;
use App\Modules\Customers\Contracts\ICustomerBillingGateway;
use App\Modules\Customers\Contracts\ICustomerJwtService;
use App\Modules\Customers\Services\CustomerPayloadPolicy;
use InvalidArgumentException;
use RuntimeException;

final readonly class Handler
{
    public function __construct(
        private ICustomerApiRepository $customers,
        private ICustomerBillingGateway $billing,
        private ICustomerJwtService $jwt,
        private CustomerPayloadPolicy $policy,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        $email = trim((string) ($command->payload['email'] ?? ''));
        $accessKey = trim((string) ($command->payload['accessKey'] ?? ''));
        $deviceName = trim((string) ($command->payload['deviceName'] ?? ''));
        $token = trim((string) ($command->payload['token'] ?? ''));

        if ($email === '' || $accessKey === '' || $deviceName === '' || $token === '') {
            $this->policy->fail(
                'Debes proporcionar email, accessKey, deviceName y token',
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->policy->fail('Formato de correo inválido');
        }

        try {
            $customer = $this->customers->login(
                $email,
                $accessKey,
                $deviceName,
                $command->ipAddress,
                $token,
            );
            $subscription = $this->billing->activeSubscription(
                (string) $customer['billingId'],
            );
        } catch (InvalidArgumentException $exception) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerValidationFailed,
                ['error' => 'Debes proporcionar email y accessKey válidos'],
                $exception,
            );
        } catch (RuntimeException $exception) {
            $this->throwLoginError($exception);
        }

        $jwt = $this->jwt->create($customer['customerId'], $token);

        return new JsonResult([
            'status' => 'success',
            'customer' => [
                'customerId' => $customer['customerId'],
                'name' => $customer['name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
                'billingId' => $customer['billingId'],
                'token' => $customer['token'],
            ],
            'apiToken' => $jwt,
            'subscriptionId' => $subscription
                ? ($subscription['subscription']['id'] ?? null)
                : null,
        ]);
    }

    private function throwLoginError(RuntimeException $exception): never
    {
        $errorCode = match ($exception->getMessage()) {
            'too_many_attempts' => ApiErrorCode::CustomerTooManyAttempts,
            'invalid_credentials' => ApiErrorCode::InvalidCredentials,
            'customer_not_waiting' => ApiErrorCode::CustomerConflict,
            'access_key_secret_missing' => ApiErrorCode::CustomerOperationFailed,
            default => throw $exception,
        };
        $message = match ($exception->getMessage()) {
            'too_many_attempts' => 'Demasiados intentos fallidos. Intenta nuevamente en una hora.',
            'invalid_credentials' => 'Credenciales inválidas',
            'customer_not_waiting' => 'El cliente no está esperando un nuevo token',
            'access_key_secret_missing' => 'No se puede validar la AccessKey. Falta configurar ACCESS_KEY_SECRET.',
        };

        throw ApiException::preservingResponse(
            $errorCode,
            ['error' => $message],
            $exception,
        );
    }
}
