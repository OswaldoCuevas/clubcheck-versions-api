<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\ValidateCustomerJwt;

use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerApiRepository;
use App\Modules\Customers\Contracts\ICustomerJwtService;

final readonly class Handler
{
    public function __construct(
        private ICustomerJwtService $jwt,
        private ICustomerApiRepository $customers,
    ) {
    }

    public function handle(Command $command): JsonResult
    {
        $token = $command->payload['token'] ?? null;

        if (
            !$token
            && $command->authorizationHeader
            && preg_match(
                '/Bearer\s+(\S+)/',
                $command->authorizationHeader,
                $matches,
            )
        ) {
            $token = $matches[1];
        }

        if (!$token && $command->queryToken !== null) {
            $token = trim($command->queryToken);
        }

        if (!$token) {
            return new JsonResult(
                [
                    'valid' => false,
                    'error' => 'Token no proporcionado',
                    'errorCode' => 'TOKEN_MISSING',
                    'customerId' => null,
                    'customer' => null,
                ],
                400,
            );
        }

        $result = $this->jwt->validate(
            (string) $token,
            $command->ipAddress,
            null,
            $command->userAgent,
        );

        if ($result['valid']) {
            $customer = $this->customers->find($result['customerId']);

            return new JsonResult([
                'valid' => true,
                'customerId' => $result['customerId'],
                'customer' => $customer !== null
                    ? [
                        'id' => $customer['customerId'],
                        'name' => $customer['name'],
                        'email' => $customer['email'],
                        'deviceName' => $customer['deviceName'],
                        'isActive' => (bool) $customer['isActive'],
                    ]
                    : null,
                'error' => null,
                'errorCode' => null,
            ]);
        }

        $errorCode = (string) $result['errorCode'];
        $statusCode = in_array(
            $errorCode,
            [
                'CUSTOMER_NOT_FOUND',
                'CUSTOMER_INACTIVE',
            ],
            true,
        ) ? 403 : 401;

        return new JsonResult(
            [
                'valid' => false,
                'customerId' => $result['customerId'],
                'error' => $this->errorMessage($errorCode),
                'errorCode' => $errorCode,
                'customer' => null,
            ],
            $statusCode,
        );
    }

    private function errorMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'TOKEN_EXPIRED' => 'El token ha expirado',
            'TOKEN_INVALID' => 'El token es inválido',
            'CUSTOMER_NOT_FOUND' => 'Cliente no encontrado',
            'CUSTOMER_INACTIVE' => 'Cliente inactivo',
            'TOKEN_MISSING' => 'Token no proporcionado',
            default => 'Error desconocido',
        };
    }
}
