<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Modules\Admin\Requests\JwtTokenRequest;
use Models\CustomerRegistryModel;

final class RevokeJwtTokenFeature
{
    public function __construct(private ?CustomerRegistryModel $registry = null)
    {
        $this->registry ??= new CustomerRegistryModel();
    }

    public function handle(JwtTokenRequest $request, string $appId): array
    {
        $customer = $this->registry->getCustomer($request->customerId);
        if (!$customer || (($customer['appId'] ?? $appId) !== $appId)) {
            throw new ForbiddenException('Cliente no pertenece a la app seleccionada');
        }

        if (!$this->registry->revokeJwtToken($request->customerId)) {
            throw new NotFoundException('Cliente no encontrado');
        }

        return [
            'success' => true,
            'message' => 'Token JWT revocado exitosamente',
        ];
    }
}
