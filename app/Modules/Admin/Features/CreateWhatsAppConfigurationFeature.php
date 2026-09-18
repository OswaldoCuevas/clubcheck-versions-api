<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\CreateWhatsAppConfigurationRequest;
use App\Services\WhatsAppService;
use Models\WhatsAppConfigurationModel;

final class CreateWhatsAppConfigurationFeature
{
    public function __construct(
        private ?WhatsAppConfigurationModel $configurations = null,
        private ?WhatsAppService $whatsApp = null
    ) {
        $this->configurations ??= new WhatsAppConfigurationModel();
        $this->whatsApp ??= new WhatsAppService();
    }

    public function handle(CreateWhatsAppConfigurationRequest $request): array
    {
        $payload = $request->getAttributes();

        if ($this->configurations->customerHasConfiguration($request->customerId)) {
            throw new ValidationException('Este cliente ya tiene una configuracion de WhatsApp');
        }

        $result = $this->configurations->create([
            'CustomerId' => $request->customerId,
            'PhoneNumber' => $request->phoneNumber,
            'PhoneNumberId' => $request->phoneNumberId,
            'AccessToken' => $payload['accessToken'] ?? null,
            'BusinessName' => $request->businessName,
            'BusinessAddress' => $payload['address'] ?? null,
            'BusinessDescription' => $payload['description'] ?? null,
            'BusinessEmail' => $payload['email'] ?? null,
            'CreatedBy' => 'admin',
        ]);

        if (!$result['success']) {
            throw new ValidationException($result['error']);
        }

        $whatsappRegistered = false;
        $whatsappError = null;

        if (!empty($payload['accessToken']) && !empty($payload['registerInWhatsApp'])) {
            $registerResult = $this->whatsApp->registerPhoneNumber($request->phoneNumberId, $payload['accessToken']);
            $whatsappRegistered = $registerResult['success'];
            $whatsappError = $registerResult['error'];
        }

        return [
            'success' => true,
            'id' => $result['id'],
            'configuration' => $this->configurations->findById($result['id']),
            'whatsappRegistered' => $whatsappRegistered,
            'whatsappError' => $whatsappError,
        ];
    }
}
