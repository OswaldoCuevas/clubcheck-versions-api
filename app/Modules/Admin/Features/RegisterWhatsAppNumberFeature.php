<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\WhatsAppConfigurationIdRequest;
use App\Services\WhatsAppService;
use Models\WhatsAppConfigurationModel;

final class RegisterWhatsAppNumberFeature
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

        $result = $this->whatsApp->registerPhoneNumber($config['PhoneNumberId'], $config['AccessToken']);

        if (!$result['success']) {
            throw new ValidationException($result['error']);
        }

        return [
            'success' => true,
            'message' => 'Numero registrado correctamente en WhatsApp',
            'response' => $result['response'] ?? null,
        ];
    }
}
