<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\StripePlanRequest;
use Models\StripePlanModel;

final class SaveStripePlanFeature
{
    public function __construct(private ?StripePlanModel $plans = null)
    {
        $this->plans ??= new StripePlanModel();
    }

    public function handle(StripePlanRequest $request, string $appId): array
    {
        if (!$this->plans->hasPlanTables() || !$this->plans->hasPriceFields()) {
            throw new ValidationException('Ejecuta primero las migraciones 009_create_stripe_plan_catalog.sql y 010_add_stripe_plan_price_fields.sql');
        }

        $payload = $request->getAttributes();
        $payload['rules'] = $this->normalizePlanRules($payload['rules'] ?? []);
        $payload['showBillingIds'] = $this->normalizeBillingIds($payload['showBillingIds'] ?? []);
        $payload['app_id'] = $appId;

        return [
            'success' => true,
            'plan' => $this->plans->savePlan($payload),
        ];
    }

    private function normalizePlanRules(array $rules): array
    {
        $normalized = [];
        foreach ($rules as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            if ($value === '__null__' || $value === '') {
                $normalized[$key] = null;
            } elseif ($value === 'true' || $value === true) {
                $normalized[$key] = true;
            } elseif ($value === 'false' || $value === false) {
                $normalized[$key] = false;
            } elseif (is_numeric($value)) {
                $normalized[$key] = (int) $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function normalizeBillingIds($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value);
        }

        return array_values(array_unique(array_filter(array_map('trim', is_array($value) ? $value : []))));
    }
}
