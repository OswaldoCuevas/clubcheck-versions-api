<?php

namespace App\Modules\Admin\Features;

use Models\CustomerRegistryModel;

final class ListCustomersFeature
{
    public function __construct(private ?CustomerRegistryModel $registry = null)
    {
        $this->registry ??= new CustomerRegistryModel();
    }

    public function handle(string $appId): array
    {
        $customers = $this->registry->getCustomers($appId);

        return [
            'count' => count($customers),
            'customers' => $customers,
        ];
    }
}
