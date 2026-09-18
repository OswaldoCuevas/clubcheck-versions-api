<?php

namespace App\Modules\Admin\Features;

use App\Services\StripeService;
use Models\StripePlanModel;

final class ListStripePlansFeature
{
    public function __construct(private ?StripePlanModel $plans = null)
    {
        $this->plans ??= new StripePlanModel();
    }

    public function handle(StripeService $stripeService, string $appId): array
    {
        $tablesReady = $this->plans->hasPlanTables() && $this->plans->hasPriceFields();
        $plans = [];
        $source = 'database';

        if ($tablesReady && $this->plans->hasPlans($appId)) {
            $plans = array_values($this->plans->getPlans(false, $appId));
        } else {
            $plans = array_values($stripeService->getConfiguredPlans());
            $source = 'config';
        }

        if (!in_array('free', array_column($plans, 'lookup_key'), true)) {
            $freePlan = $stripeService->getPlanRulesByLookupKey('free');
            if ($freePlan) {
                array_unshift($plans, $freePlan + [
                    'unit_amount' => null,
                    'currency' => 'mxn',
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
            }
        }

        $stripeLookup = $stripeService->findPricesByLookupKeys(array_column($plans, 'lookup_key'));
        if (!($stripeLookup['success'] ?? false)) {
            $pricesByLookupKey = [];
            foreach (array_column($plans, 'lookup_key') as $lookupKey) {
                $single = $stripeService->findPriceByLookupKey($lookupKey);
                if (($single['success'] ?? false) && ($single['exists'] ?? false) && !empty($single['price'])) {
                    $pricesByLookupKey[$lookupKey] = $single['price'];
                }
            }
            $stripeLookup = [
                'success' => true,
                'prices' => $pricesByLookupKey,
                'fallback' => true,
            ];
        }

        if ($stripeLookup['success'] ?? false) {
            $pricesByLookupKey = $stripeLookup['prices'] ?? [];
            foreach ($plans as &$plan) {
                $stripePrice = $pricesByLookupKey[$plan['lookup_key'] ?? ''] ?? null;
                $plan['stripe_exists'] = $stripePrice !== null;
                $plan['stripe_price'] = $stripePrice;

                if ($stripePrice && empty($plan['stripe_price_id'])) {
                    $plan['stripe_price_id'] = $stripePrice['id'] ?? null;
                    if ($tablesReady && $source === 'database' && !empty($stripePrice['id'])) {
                        $this->plans->setStripePriceId($plan['lookup_key'], $stripePrice['id'], $appId);
                    }
                }
            }
            unset($plan);
        }

        return [
            'success' => true,
            'plans' => $plans,
            'rules_catalog' => $tablesReady ? $this->plans->getRuleCatalog($appId) : [],
            'tables_ready' => $tablesReady,
            'source' => $source,
            'stripe_checked' => $stripeLookup['success'] ?? false,
            'stripe_dashboard_base' => ($_ENV['APP_MODE'] ?? 'DEV') === 'PROD'
                ? 'https://dashboard.stripe.com/prices/'
                : 'https://dashboard.stripe.com/test/prices/',
        ];
    }
}
