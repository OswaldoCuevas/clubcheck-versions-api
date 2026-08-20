<?php

namespace App\Services\WhatsApp;

require_once __DIR__ . '/../../enums/WhatsAppEvent.php';

use App\Enums\WhatsAppEvent;

interface WhatsAppTemplateStrategyInterface
{
    public function resolveTemplateMessage(
        WhatsAppEvent $event,
        array $parameters,
        array $defaultTemplate,
        array $defaultComponents,
        string $customerApiId
    ): array;
}
