<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\WhatsAppTemplateIdRequest;
use Models\WhatsAppTemplateModel;

final class DeleteWhatsAppTemplateFeature
{
    public function __construct(private ?WhatsAppTemplateModel $templates = null)
    {
        $this->templates ??= new WhatsAppTemplateModel();
    }

    public function handle(WhatsAppTemplateIdRequest $request): array
    {
        return $this->templates->deleteTemplate($request->id);
    }
}
