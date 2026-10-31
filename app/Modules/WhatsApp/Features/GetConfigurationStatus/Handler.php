<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\GetConfigurationStatus;

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

    public function handle(Query $query): JsonResult
    {
        $config = $this->configurations->configuration($query->id);
        if (!$config) {
            return new JsonResult(['error' => 'Configuración no encontrada'], 404);
        }

        if (empty($config['AccessToken'])) {
            return new JsonResult(['error' => 'Esta configuración no tiene Access Token'], 422);
        }

        $result = $this->whatsApp->getPhoneNumberStatus(
            $config['PhoneNumberId'],
            $config['AccessToken'],
        );

        return new JsonResult([
            'success' => $result['success'],
            'status' => $result['status'],
            'data' => $result['data'] ?? null,
            'error' => $result['error'],
        ]);
    }
}
