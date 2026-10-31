<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\CreateConfiguration;

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
        $p = $command->payload;
        foreach (['customerId', 'phoneNumber', 'phoneNumberId', 'businessName'] as $field) {
            if (empty($p[$field])) {
                return new JsonResult(['error' => "Campo requerido: {$field}"], 422);
            }
        }
        if ($this->configurations->hasConfiguration($p['customerId'])) {
            return new JsonResult(['error' => 'Este cliente ya tiene una configuración de WhatsApp'], 422);
        }
        $result = $this->configurations->createConfiguration([
            'CustomerId' => $p['customerId'],
            'PhoneNumber' => $p['phoneNumber'],
            'PhoneNumberId' => $p['phoneNumberId'],
            'AccessToken' => $p['accessToken'] ?? null,
            'BusinessName' => $p['businessName'],
            'BusinessAddress' => $p['address'] ?? null,
            'BusinessDescription' => $p['description'] ?? null,
            'BusinessEmail' => $p['email'] ?? null,
            'CreatedBy' => 'admin',
        ]);
        if (!$result['success']) {
            return new JsonResult(['error' => $result['error']], 422);
        }

        $registered = false;
        $error = null;
        if (!empty($p['accessToken']) && !empty($p['registerInWhatsApp'])) {
            $registration = $this->whatsApp->registerPhoneNumber($p['phoneNumberId'], $p['accessToken']);
            $registered = $registration['success'];
            $error = $registration['error'];
        }
        return new JsonResult([
            'success' => true,
            'id' => $result['id'],
            'configuration' => $this->configurations->configuration($result['id']),
            'whatsappRegistered' => $registered,
            'whatsappError' => $error,
        ]);
    }
}
