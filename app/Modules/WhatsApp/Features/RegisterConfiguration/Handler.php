<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\RegisterConfiguration;

use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;
use App\Services\WhatsAppService;

final readonly class Handler
{
    public function __construct(
        private IWhatsAppAdminRepository $configurations,
        private WhatsAppService $whatsApp,
    )
    {
    }

    public function handle(Command $command): JsonResult
    {
        $config = $this->configurations->configuration($command->id);
        if (!$config) {
            return new JsonResult(['error' => 'Configuración no encontrada'], 404);
        }

        if (empty($config['AccessToken'])) {
            return new JsonResult(['error' => 'Esta configuración no tiene Access Token'], 422);
        }

        $result = $this->whatsApp->registerPhoneNumber(
            $config['PhoneNumberId'],
            $config['AccessToken'],
        );

        if (!$result['success']) {
            return new JsonResult(
                [
                    'success' => false,
                    'error' => $result['error'],
                    'httpCode' => $result['httpCode'] ?? null,
                ],
                422,
            );
        }

        return new JsonResult([
            'success' => true,
            'message' => 'Número registrado correctamente en WhatsApp',
            'response' => $result['response'] ?? null,
        ]);
    }
}
