<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\DashboardSettingsRequest;
use App\Services\AdminDashboardService;

final class SaveDashboardSettingsFeature
{
    public function __construct(private ?AdminDashboardService $dashboard = null)
    {
        $this->dashboard ??= new AdminDashboardService();
    }

    public function handle(DashboardSettingsRequest $request, string $appId): array
    {
        $this->dashboard->updateWhatsappMessageCost($request->whatsappMessageUnitCostMxn, $appId);

        return ['success' => true];
    }
}
