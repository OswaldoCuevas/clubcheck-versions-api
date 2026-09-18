<?php

namespace App\Modules\Admin\Requests;

final class DashboardSettingsRequest extends AdminRequest
{
    public readonly float $whatsappMessageUnitCostMxn;

    public function __construct()
    {
        parent::__construct(['POST']);

        $this->whatsappMessageUnitCostMxn = (float) ($this->payload['whatsapp_message_unit_cost_mxn'] ?? 0);
    }
}
