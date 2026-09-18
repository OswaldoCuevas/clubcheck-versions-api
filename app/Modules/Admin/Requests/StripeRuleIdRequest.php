<?php

namespace App\Modules\Admin\Requests;

final class StripeRuleIdRequest extends AdminRequest
{
    public readonly int $ruleId;

    public function __construct(string $ruleId)
    {
        parent::__construct(['DELETE', 'POST'], ['ruleId' => $ruleId]);

        $this->ruleId = (int) $this->routeParam('ruleId');
    }
}
