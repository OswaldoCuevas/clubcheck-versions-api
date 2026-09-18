<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ForbiddenException;
use App\Modules\Admin\Requests\CustomerIdRequest;
use Models\CustomerRegistryModel;

final class GetCustomerIpsFeature
{
    public function __construct(
        private ?CustomerRegistryModel $registry = null,
        private ?\Models\CustomerIpLogModel $ipLogs = null
    ) {
        $this->registry ??= new CustomerRegistryModel();
        $this->ipLogs ??= new \Models\CustomerIpLogModel();
    }

    public function handle(CustomerIdRequest $request, string $appId): array
    {
        $customer = $this->registry->getCustomer($request->customerId);
        if (!$customer || (($customer['appId'] ?? $appId) !== $appId)) {
            throw new ForbiddenException('Cliente no pertenece a la app seleccionada');
        }

        return [
            'success' => true,
            'customerId' => $request->customerId,
            'hasMultipleRecentIps' => $this->ipLogs->hasMultipleRecentIps($request->customerId),
            'ips' => $this->ipLogs->getCustomerIps($request->customerId),
        ];
    }
}
