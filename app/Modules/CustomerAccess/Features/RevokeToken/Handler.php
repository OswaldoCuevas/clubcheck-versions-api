<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\RevokeToken;

use App\Http\JsonResult;
use App\Modules\CustomerAccess\Contracts\ICustomerAccessRepository;

final readonly class Handler
{
    public function __construct(private ICustomerAccessRepository $customers)
    {
    }
    public function handle(Command $command): JsonResult
    {
        $id = trim((string)($command->payload['customerId'] ?? ''));
        if ($id === '') {
            return new JsonResult(['error' => 'customerId es obligatorio'], 422);
        }
        if (!$this->customers->revokeToken($id)) {
            return new JsonResult(
                [
                    'success' => false,
                    'error' => 'Cliente no encontrado',
                ],
                404,
            );
        }

        return new JsonResult([
            'success' => true,
            'message' => 'Token JWT revocado exitosamente',
        ]);
    }
}
