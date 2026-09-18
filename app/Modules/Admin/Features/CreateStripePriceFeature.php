<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\StripeLookupKeyRequest;
use App\Services\StripeService;
use Models\StripePlanModel;

final class CreateStripePriceFeature
{
    public function __construct(private ?StripePlanModel $plans = null)
    {
        $this->plans ??= new StripePlanModel();
    }

    public function handle(StripeLookupKeyRequest $request, StripeService $stripeService, ?string $productId, string $appId): array
    {
        if (!$this->plans->hasPlanTables() || !$this->plans->hasPriceFields()) {
            throw new ValidationException('Ejecuta primero las migraciones de planes Stripe');
        }

        $plan = $this->plans->getPlanByLookupKey($request->lookupKey, false, $appId);
        if (!$plan) {
            throw new NotFoundException('Plan no encontrado');
        }

        $result = $stripeService->createStripePriceFromPlan($plan, $productId);
        if (($result['success'] ?? false) && !empty($result['price']['id'])) {
            $this->plans->setStripePriceId($request->lookupKey, $result['price']['id'], $appId);
        }

        return [
            'payload' => $result,
            'httpStatus' => ($result['success'] ?? false) ? 200 : 400,
        ];
    }
}
