<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\StripeLookupKeyRequest;
use App\Services\StripeService;
use Models\StripePlanModel;

final class VerifyStripePlanFeature
{
    public function __construct(private ?StripePlanModel $plans = null)
    {
        $this->plans ??= new StripePlanModel();
    }

    public function handle(StripeLookupKeyRequest $request, StripeService $stripeService, string $appId): array
    {
        $result = $stripeService->findPriceByLookupKey($request->lookupKey);
        if (($result['success'] ?? false) && ($result['exists'] ?? false) && !empty($result['price']['id'])) {
            try {
                if ($this->plans->hasPlanTables()) {
                    $this->plans->setStripePriceId($request->lookupKey, $result['price']['id'], $appId);
                }
            } catch (\Throwable) {
            }
        }

        return [
            'payload' => $result,
            'httpStatus' => ($result['success'] ?? false) ? 200 : 400,
        ];
    }
}
