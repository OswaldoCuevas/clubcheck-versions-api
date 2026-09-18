<?php

namespace App\Modules\Admin\Requests;

use App\Enums\WhatsAppEvent;
use App\Exceptions\ValidationException;

final class CreateWhatsAppTemplateRequest extends AdminRequest
{
    public readonly string $customerId;
    public readonly string $templateName;
    public readonly string $languageCode;
    public readonly string $eventKey;
    public readonly array $components;

    public function __construct()
    {
        parent::__construct(['POST']);

        $this->customerId = $this->payloadString('customerId');
        $this->templateName = $this->payloadString('templateName');
        $this->languageCode = $this->optionalString('languageCode') ?: 'es_MX';
        $this->eventKey = $this->optionalString('eventKey') ?: '';
        $this->components = $this->payload['components'] ?? [];

        if (!is_array($this->components)) {
            throw new ValidationException('components debe ser un array');
        }

        if ($this->eventKey !== '' && !WhatsAppEvent::tryFrom($this->eventKey)) {
            throw new ValidationException('Evento de WhatsApp invalido');
        }
    }
}
