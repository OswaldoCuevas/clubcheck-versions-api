<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\StripeRuleIdRequest;
use Models\StripePlanModel;

final class UnlinkStripePlanRuleFeature
{
    public function __construct(private ?StripePlanModel $plans = null)
    {
        $this->plans ??= new StripePlanModel();
    }

    public function handle(StripeRuleIdRequest $request, string $appId): array
    {
        $this->plans->unlinkRuleFromApp($request->ruleId, $appId);

        return ['success' => true];
    }
}
