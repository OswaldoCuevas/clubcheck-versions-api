<?php

namespace App\Services\WhatsApp;

require_once __DIR__ . '/WhatsAppTemplateStrategyInterface.php';
require_once __DIR__ . '/WhatsAppTemplateComponentBuilder.php';
require_once __DIR__ . '/../../Models/WhatsAppTemplateModel.php';

use App\Enums\WhatsAppEvent;
use Models\WhatsAppTemplateModel;

class CustomerWhatsAppTemplateStrategy implements WhatsAppTemplateStrategyInterface
{
    public function __construct(
        private WhatsAppTemplateModel $templateModel,
        private WhatsAppTemplateStrategyInterface $fallbackStrategy,
        private ?WhatsAppTemplateComponentBuilder $componentBuilder = null
    ) {
        $this->componentBuilder ??= new WhatsAppTemplateComponentBuilder();
    }

    public function resolveTemplateMessage(
        WhatsAppEvent $event,
        array $parameters,
        array $defaultTemplate,
        array $defaultComponents,
        string $customerApiId
    ): array {
        // Busca la relacion customer + evento. Cuando el customer tiene API propia,
        // no se usa fallback default: debe tener un template dado de alta para el evento.
        $template = $this->templateModel->findActiveForEvent($customerApiId, $event);

        if (!$template) {
            return [
                'strategy' => 'customer',
                'errorMessage' => "Template personalizado no configurado para {$event->label()}.",
                'description' => "Template personalizado {$event->label()}",
            ];
        }

        return [
            'strategy' => 'customer',
            'name' => $template['TemplateName'],
            'language' => $template['LanguageCode'] ?: 'es_MX',
            'components' => $this->componentBuilder->build($template['Components'] ?? [], $parameters),
            'description' => "Template personalizado {$event->label()}: {$template['TemplateName']}",
        ];
    }
}
