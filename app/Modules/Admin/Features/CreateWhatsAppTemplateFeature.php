<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\CreateWhatsAppTemplateRequest;
use Models\WhatsAppTemplateModel;

final class CreateWhatsAppTemplateFeature
{
    public function __construct(private ?WhatsAppTemplateModel $templates = null)
    {
        $this->templates ??= new WhatsAppTemplateModel();
    }

    public function handle(CreateWhatsAppTemplateRequest $request): array
    {
        $payload = $request->getAttributes();
        $result = $this->templates->createTemplate([
            'CustomerId' => $request->customerId,
            'TemplateName' => $request->templateName,
            'LanguageCode' => $request->languageCode,
            'Description' => $payload['description'] ?? null,
            'ComponentsJson' => $request->components,
            'EventKey' => $request->eventKey,
            'CreatedBy' => 'admin',
        ]);

        if (!$result['success']) {
            throw new ValidationException($result['error']);
        }

        return ['success' => true, 'id' => $result['id']];
    }
}
