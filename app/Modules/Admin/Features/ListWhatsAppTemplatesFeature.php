<?php

namespace App\Modules\Admin\Features;

use App\Enums\WhatsAppEvent;
use Models\WhatsAppTemplateModel;

final class ListWhatsAppTemplatesFeature
{
    public function __construct(private ?WhatsAppTemplateModel $templates = null)
    {
        $this->templates ??= new WhatsAppTemplateModel();
    }

    public function handle(): array
    {
        return [
            'success' => true,
            'events' => WhatsAppEvent::options(),
            'variables' => $this->templates->getVariables(),
            'templates' => $this->templates->getAllWithEvents(),
        ];
    }
}
