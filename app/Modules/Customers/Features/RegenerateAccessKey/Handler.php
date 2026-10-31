<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\RegenerateAccessKey;

use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerRepository;

final readonly class Handler
{
    public function __construct(private ICustomerRepository $customers)
    {
    }
    public function handle(Command $command): JsonResult
    {
        $customerId = trim((string) ($command->payload['customerId'] ?? ''));
        if ($customerId === '') {
            return new JsonResult(['error' => 'customerId es obligatorio'], 422);
        }
        $result = $this->customers->regenerateAccessKey($customerId);
        if (!$result) {
            return new JsonResult(['error' => 'Cliente no encontrado'], 404);
        }
        return new JsonResult([
            'status' => 'regenerated',
            'customerId' => $result['customerId'],
            'accessKey' => $result['accessKey'],
            'customer' => $result['customer'],
        ]);
    }
}
