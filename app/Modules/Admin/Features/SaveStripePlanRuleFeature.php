<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\StripePlanRequest;
use Models\StripePlanModel;

final class SaveStripePlanRuleFeature
{
    public function __construct(private ?StripePlanModel $plans = null)
    {
        $this->plans ??= new StripePlanModel();
    }

    public function handle(StripePlanRequest $request, string $appId): array
    {
        if (!$this->plans->hasPlanTables()) {
            throw new ValidationException('Ejecuta primero las migraciones de planes Stripe');
        }

        if (!$this->plans->hasRuleCatalogAppField()) {
            throw new ValidationException('Ejecuta la migracion 015_make_stripe_rule_catalog_app_specific.sql para administrar reglas por app');
        }

        return [
            'success' => true,
            'rule' => $this->plans->saveRuleCatalogEntry($request->getAttributes(), $appId),
        ];
    }
}
