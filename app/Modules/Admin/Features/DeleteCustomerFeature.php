<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Modules\Admin\Requests\CustomerIdRequest;
use Models\CustomerRegistryModel;

final class DeleteCustomerFeature
{
    public function __construct(private ?CustomerRegistryModel $registry = null)
    {
        $this->registry ??= new CustomerRegistryModel();
    }

    public function handle(CustomerIdRequest $request): array
    {
        if (!$this->registry->deleteCustomer($request->customerId)) {
            throw new NotFoundException('Cliente no encontrado');
        }

        return [
            'success' => true,
            'message' => 'Cliente eliminado correctamente',
        ];
    }
}
