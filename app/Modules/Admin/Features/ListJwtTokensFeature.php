<?php

namespace App\Modules\Admin\Features;

use Models\CustomerRegistryModel;

final class ListJwtTokensFeature
{
    public function __construct(
        private ?CustomerRegistryModel $registry = null,
        private ?\Models\CustomerIpLogModel $ipLogs = null
    ) {
        $this->registry ??= new CustomerRegistryModel();
        $this->ipLogs ??= new \Models\CustomerIpLogModel();
    }

    public function handle(string $appId): array
    {
        return [
            'success' => true,
            'stats' => $this->registry->getJwtStats($appId),
            'customers' => $this->ipLogs->getCustomerIpSummary($appId),
        ];
    }
}
