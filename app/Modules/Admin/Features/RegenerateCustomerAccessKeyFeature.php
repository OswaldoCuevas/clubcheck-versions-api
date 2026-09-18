<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Modules\Admin\Requests\CustomerIdRequest;
use Models\CustomerRegistryModel;

final class RegenerateCustomerAccessKeyFeature
{
    public function __construct(private ?CustomerRegistryModel $registry = null)
    {
        $this->registry ??= new CustomerRegistryModel();
    }

    public function handle(CustomerIdRequest $request): array
    {
        $result = $this->registry->regenerateAccessKey($request->customerId);

        if (!$result) {
            throw new NotFoundException('Cliente no encontrado');
        }

        return [
            'status' => 'regenerated',
            'customerId' => $result['customerId'],
            'accessKey' => $result['accessKey'],
            'customer' => $result['customer'],
        ];
    }
}
