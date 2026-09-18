<?php

namespace App\Modules\Admin\Features;

use App\Services\AdminDashboardService;
use App\Services\StripeService;

final class GetDashboardFeature
{
    public function __construct(private ?AdminDashboardService $dashboard = null)
    {
        $this->dashboard ??= new AdminDashboardService();
    }

    public function handle(StripeService $stripeService, string $appId): array
    {
        return [
            'success' => true,
            'dashboard' => $this->dashboard->getDashboard($stripeService, $appId),
            'stripe_dashboard_url' => ($_ENV['APP_MODE'] ?? 'DEV') === 'PROD'
                ? 'https://dashboard.stripe.com/'
                : 'https://dashboard.stripe.com/test/',
        ];
    }
}
