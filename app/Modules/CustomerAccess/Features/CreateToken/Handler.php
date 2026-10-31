<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\CreateToken;

use App\Http\JsonResult;
use App\Modules\CustomerAccess\Contracts\ICustomerAccessRepository;
use App\Services\JwtService;
use RuntimeException;

final readonly class Handler
{
    public function __construct(
        private ICustomerAccessRepository $access,
        private JwtService $jwt,
    )
    {
    }
    public function handle(Command $command): JsonResult
    {
        $id = trim((string)($command->payload['customerId'] ?? ''));
        $expires = isset($command->payload['expiresIn']) ? (int)$command->payload['expiresIn'] : null;
        if ($id === '') {
            return new JsonResult(['error' => 'customerId es obligatorio'], 422);
        }
        try {
            $customer = $this->access->tokenCustomer($id);
            if (!$customer || !$customer['IsActive'] || !$customer['Token']) {
                return new JsonResult(
                    [
                        'success' => false,
                        'error' => 'No se pudo crear el token. Verifique que el cliente exista, '
                            . 'esté activo y tenga un token de máquina registrado.',
                    ],
                    422,
                );
            }
            $duration = $expires ?? 2592000;
            $expiresAt = $duration === 0 ? null : time() + $duration;
            $token = $this->jwt->createToken(
                [
                    'cid' => $id,
                    'mkt' => $customer['Token'],
                    'typ' => 'customer',
                ],
                $duration,
            );
            $this->access->storeToken($id, $token, $expiresAt);
            $result = [
                'expiresAt' => $expiresAt,
                'expiresIn' => $duration,
            ];

            return new JsonResult([
                'success' => true,
                'message' => 'Token JWT creado exitosamente',
                'data' => [
                    'customerId' => $id,
                    'expiresAt' => $result['expiresAt'],
                    'expiresIn' => $result['expiresIn'],
                ],
            ]);
        } catch (RuntimeException $e) {
            $messages = [
                'customer_not_found' => 'Cliente no encontrado',
                'customer_inactive' => 'El cliente está inactivo',
                'machine_token_mismatch' => 'El token de máquina no coincide',
            ];

            return new JsonResult(
                [
                    'success' => false,
                    'error' => $messages[$e->getMessage()] ?? $e->getMessage(),
                ],
                422,
            );
        }
    }
}
