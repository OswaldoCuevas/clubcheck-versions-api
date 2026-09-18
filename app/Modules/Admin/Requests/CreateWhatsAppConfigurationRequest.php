<?php

namespace App\Modules\Admin\Requests;

final class CreateWhatsAppConfigurationRequest extends AdminRequest
{
    public readonly string $customerId;
    public readonly string $phoneNumber;
    public readonly string $phoneNumberId;
    public readonly string $businessName;

    public function __construct()
    {
        parent::__construct(['POST']);

        $this->customerId = $this->payloadString('customerId', null, 'Campo requerido: customerId');
        $this->phoneNumber = $this->payloadString('phoneNumber', null, 'Campo requerido: phoneNumber');
        $this->phoneNumberId = $this->payloadString('phoneNumberId', null, 'Campo requerido: phoneNumberId');
        $this->businessName = $this->payloadString('businessName', null, 'Campo requerido: businessName');
    }
}
