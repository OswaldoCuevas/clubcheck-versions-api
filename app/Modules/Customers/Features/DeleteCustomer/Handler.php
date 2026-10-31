<?php

declare(strict_types=1);

namespace App\Modules\Customers\Features\DeleteCustomer;

use App\Http\JsonResult;
use App\Modules\Customers\Contracts\ICustomerRepository;

final readonly class Handler
{
    public function __construct(private ICustomerRepository $customers)
    {
    }
    public function handle(Command $command): JsonResult
    {
        $id = trim($command->customerId);
        if ($id === '') {
            return new JsonResult(['error' => 'customerId es obligatorio'], 422);
        }
        if (!$this->customers->delete($id)) {
            return new JsonResult(['error' => 'Cliente no encontrado'], 404);
        }
        return new JsonResult([
            'success' => true,
            'message' => 'Cliente eliminado correctamente',
        ]);
    }
}
