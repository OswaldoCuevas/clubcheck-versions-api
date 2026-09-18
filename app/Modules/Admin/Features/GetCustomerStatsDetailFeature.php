<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Modules\Admin\Requests\CustomerIdRequest;
use App\Services\CustomerStatsService;
use Models\CustomerRegistryModel;

final class GetCustomerStatsDetailFeature
{
    public function __construct(
        private ?CustomerRegistryModel $registry = null,
        private ?CustomerStatsService $stats = null
    ) {
        $this->registry ??= new CustomerRegistryModel();
        $this->stats ??= new CustomerStatsService();
    }

    public function handle(CustomerIdRequest $request, string $appId): array
    {
        $customer = $this->registry->getCustomer($request->customerId);

        if (!$customer) {
            throw new NotFoundException('Cliente no encontrado');
        }

        if (($customer['appId'] ?? $appId) !== $appId) {
            throw new ForbiddenException('Cliente no pertenece a la app seleccionada');
        }

        return [
            'customer' => $customer,
            'stats' => $this->stats->getCustomerStats($request->customerId),
            'generatedAt' => date('Y-m-d H:i:s'),
        ];
    }
}
