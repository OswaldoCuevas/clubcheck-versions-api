<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ValidateCustomer;

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
        $requestedCustomerId = trim((string) ($payload['customerId'] ?? ''));
        $customerId = $command->authenticatedCustomerId
            ?? $requestedCustomerId;
        $name = trim((string) ($payload['name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $billingId = $this->nullableString($payload, 'billingId');
        $planCode = $this->nullableString($payload, 'planCode');
        $phone = $this->nullableString($payload, 'phone');
        $deviceName = $this->nullableString($payload, 'deviceName');
        $token = trim((string) ($payload['token'] ?? ''));

        if ($name === '' || $token === '') {
            $this->policy->fail('El nombre y el token son obligatorios');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->policy->fail('Formato de correo inválido');
        }

        if ($planCode !== null && mb_strlen($planCode) > 50) {
            $this->policy->fail('El PlanCode debe tener máximo 50 caracteres');
        }

        $existing = $customerId !== ''
            ? $this->customers->find($customerId)
            : null;
        $normalizedEmail = $email !== '' ? $email : null;

        if (
            $existing === null
            && !$this->customers->isEmailAvailable($normalizedEmail)
        ) {
            throw ApiException::preservingResponse(
                ApiErrorCode::CustomerConflict,
                [
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ],
            );
        }

        $privacyAcceptance = $this->policy->acceptance(
            $payload['privacyAcceptance'] ?? null,
            $existing === null,
            false,
        );
        $normalizedCustomerId = $existing['customerId']
            ?? ($customerId !== '' ? $customerId : null);

        return new JsonResult([
            'valid' => true,
            'found' => $existing !== null,
            'customerId' => $normalizedCustomerId,
            'normalized' => [
                'customerId' => $normalizedCustomerId,
                'name' => $name,
                'billingId' => $existing['billingId'] ?? $billingId,
                'planCode' => $existing['planCode'] ?? $planCode,
                'email' => $normalizedEmail,
                'phone' => $phone,
                'deviceName' => $deviceName,
                'token' => $token,
                'privacyAcceptance' => $privacyAcceptance,
            ],
            'customer' => $existing,
        ]);
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
}
