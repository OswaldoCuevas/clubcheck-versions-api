<?php

declare(strict_types=1);

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Contracts\ICustomerApiRepository;
use App\Modules\Customers\Contracts\ICustomerJwtService;
use App\Services\JwtService;

final readonly class CustomerJwtService implements ICustomerJwtService
{
    public function __construct(
        private JwtService $jwt,
        private ICustomerApiRepository $customers,
    ) {
    }

    public function create(string $customerId, string $machineToken): string
    {
        $token = $this->jwt->createToken(
            [
                'cid' => $customerId,
                'mkt' => $machineToken,
                'typ' => 'customer',
            ],
            0,
        );
        $this->customers->storeJwt($customerId, $token);

        return $token;
    }

    public function validate(
        string $jwt,
        ?string $ipAddress,
        ?string $deviceName,
        ?string $userAgent,
    ): array
    {
        $payload = $this->jwt->validateToken($jwt);

        if ($payload === null) {
            $tokenInformation = $this->jwt->getTokenInfo($jwt);

            if (
                $tokenInformation['valid_format']
                && $tokenInformation['is_expired']
            ) {
                return $this->failure(
                    'TOKEN_EXPIRED',
                    $tokenInformation['payload']['cid'] ?? null,
                );
            }

            return $this->failure('TOKEN_INVALID');
        }

        if (($payload['typ'] ?? '') !== 'customer') {
            return $this->failure('TOKEN_TYPE_INVALID');
        }

        $customerId = $payload['cid'] ?? null;
        $machineToken = $payload['mkt'] ?? null;

        if (!$customerId || !$machineToken) {
            return $this->failure('TOKEN_INCOMPLETE');
        }

        $customer = $this->customers->jwtState((string) $customerId);

        if ($customer === null) {
            return $this->failure('CUSTOMER_NOT_FOUND', (string) $customerId);
        }

        if (empty($customer['IsActive'])) {
            return $this->failure('CUSTOMER_INACTIVE', (string) $customerId);
        }

        if (($customer['TokenJwt'] ?? null) !== $jwt) {
            return $this->failure('TOKEN_NOT_CURRENT', (string) $customerId);
        }

        if (($customer['Token'] ?? null) !== $machineToken) {
            return $this->failure('MACHINE_TOKEN_MISMATCH', (string) $customerId);
        }

        $this->customers->recordJwtAccess(
            (string) $customerId,
            $ipAddress,
            $deviceName,
            $userAgent,
        );

        return [
            'valid' => true,
            'customerId' => $customerId,
            'machineToken' => $machineToken,
            'errorCode' => null,
            'payload' => $payload,
        ];
    }

    /** @return array<string, mixed> */
    private function failure(
        string $errorCode,
        ?string $customerId = null,
    ): array
    {
        return [
            'valid' => false,
            'customerId' => $customerId,
            'errorCode' => $errorCode,
            'payload' => null,
        ];
    }
}
