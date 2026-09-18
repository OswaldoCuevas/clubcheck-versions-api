<?php

namespace App\Modules\Admin\Features;

use App\Services\CustomerStatsService;

final class GetCustomerStatsFeature
{
    public function __construct(private ?CustomerStatsService $stats = null)
    {
        $this->stats ??= new CustomerStatsService();
    }

    public function handle(string $appId): array
    {
        return [
            'global' => $this->stats->getGlobalStats($appId),
            'customers' => $this->stats->getAllCustomersStats($appId),
            'generatedAt' => date('Y-m-d H:i:s'),
        ];
    }
}
