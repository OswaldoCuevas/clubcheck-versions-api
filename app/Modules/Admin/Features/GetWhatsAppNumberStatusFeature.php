<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\WhatsAppConfigurationIdRequest;
use App\Services\WhatsAppService;
use Models\WhatsAppConfigurationModel;

final class GetWhatsAppNumberStatusFeature
{
    public function __construct(
        private ?WhatsAppConfigurationModel $configurations = null,
        private ?WhatsAppService $whatsApp = null
    ) {
        $this->configurations ??= new WhatsAppConfigurationModel();
        $this->whatsApp ??= new WhatsAppService();
    }

    public function handle(WhatsAppConfigurationIdRequest $request): array
    {
        $config = $this->configurations->findById($request->id);

        if (!$config) {
            throw new NotFoundException('Configuracion no encontrada');
        }

        if (empty($config['AccessToken'])) {
            throw new ValidationException('Esta configuracion no tiene Access Token');
        }

        $result = $this->whatsApp->getPhoneNumberStatus($config['PhoneNumberId'], $config['AccessToken']);

        return [
            'success' => $result['success'],
            'status' => $result['status'],
            'data' => $result['data'] ?? null,
            'error' => $result['error'],
        ];
    }
}
