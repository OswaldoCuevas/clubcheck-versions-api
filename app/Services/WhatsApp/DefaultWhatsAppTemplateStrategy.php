<?php

namespace App\Services\WhatsApp;

require_once __DIR__ . '/WhatsAppTemplateStrategyInterface.php';
require_once __DIR__ . '/WhatsAppTemplateComponentBuilder.php';

use App\Enums\WhatsAppEvent;

class DefaultWhatsAppTemplateStrategy implements WhatsAppTemplateStrategyInterface
{
    public function __construct(private ?WhatsAppTemplateComponentBuilder $componentBuilder = null)
    {
        $this->componentBuilder ??= new WhatsAppTemplateComponentBuilder();
    }

    public function resolveTemplateMessage(
        WhatsAppEvent $event,
        array $parameters,
        array $defaultTemplate,
        array $defaultComponents,
        string $customerApiId
    ): array {
        // Estrategia default: siempre usa el template configurado en config/whatsapp.php
        // y arma los componentes declarados por el metodo del evento.
        return [
            'strategy' => 'default',
            'name' => $defaultTemplate['name'],
            'language' => $defaultTemplate['language'],
            'components' => $this->componentBuilder->build($defaultComponents, $parameters),
        ];
    }
}
