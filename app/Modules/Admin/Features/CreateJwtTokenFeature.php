<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\JwtTokenRequest;
use App\Services\CustomerJwtService;
use Models\CustomerRegistryModel;

final class CreateJwtTokenFeature
{
    public function __construct(
        private ?CustomerRegistryModel $registry = null,
        private ?CustomerJwtService $jwtService = null
    ) {
        $this->registry ??= new CustomerRegistryModel();
        $this->jwtService ??= new CustomerJwtService();
    }

    public function handle(JwtTokenRequest $request, string $appId): array
    {
        $customer = $this->registry->getCustomer($request->customerId);
        if (!$customer || (($customer['appId'] ?? $appId) !== $appId)) {
            throw new ForbiddenException('Cliente no pertenece a la app seleccionada');
        }

        try {
            $result = $this->jwtService->renewCustomerToken($request->customerId, $request->expiresIn);
        } catch (\RuntimeException $e) {
            $messages = [
                'customer_not_found' => 'Cliente no encontrado',
                'customer_inactive' => 'El cliente esta inactivo',
                'machine_token_mismatch' => 'El token de maquina no coincide',
            ];

            throw new ValidationException($messages[$e->getMessage()] ?? $e->getMessage());
        }

        if (!$result) {
            throw new ValidationException('No se pudo crear el token. Verifique que el cliente exista, este activo y tenga un token de maquina registrado.');
        }

        return [
            'success' => true,
            'message' => 'Token JWT creado exitosamente',
            'data' => [
                'customerId' => $request->customerId,
                'expiresAt' => $result['expiresAt'],
                'expiresIn' => $result['expiresIn'],
            ],
        ];
    }
}
