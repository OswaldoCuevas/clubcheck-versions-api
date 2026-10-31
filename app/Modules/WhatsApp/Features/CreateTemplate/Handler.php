<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\CreateTemplate;

use App\Enums\WhatsAppEvent;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;

final readonly class Handler
{
    public function __construct(private IWhatsAppAdminRepository $templates)
    {
    }

    public function handle(Command $command): JsonResult
    {
        $p = $command->payload;
        $customerId = trim((string)($p['customerId'] ?? ''));
        $name = trim((string)($p['templateName'] ?? ''));
        $language = trim((string)($p['languageCode'] ?? 'es_MX')) ?: 'es_MX';
        $event = trim((string)($p['eventKey'] ?? ''));
        $components = $p['components'] ?? [];
        if ($customerId === '' || $name === '') {
            return new JsonResult(['error' => 'customerId y templateName son requeridos'], 422);
        }

        if (!is_array($components)) {
            return new JsonResult(['error' => 'components debe ser un array'], 422);
        }

        if ($event !== '' && !WhatsAppEvent::tryFrom($event)) {
            return new JsonResult(['error' => 'Evento de WhatsApp invalido'], 422);
        }

        $result = $this->templates->createTemplate([
            'CustomerId' => $customerId,
            'TemplateName' => $name,
            'LanguageCode' => $language,
            'Description' => $p['description'] ?? null,
            'ComponentsJson' => $components,
            'EventKey' => $event,
            'CreatedBy' => 'admin',
        ]);

        if (!$result['success']) {
            return new JsonResult(['error' => $result['error']], 422);
        }

        return new JsonResult([
            'success' => true,
            'id' => $result['id'],
        ]);
    }
}
