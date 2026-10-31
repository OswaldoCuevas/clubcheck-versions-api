<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ListTemplates;

use App\Enums\WhatsAppEvent;
use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;

final readonly class Handler
{
    public function __construct(private IWhatsAppAdminRepository $templates)
    {
    }

    public function handle(Query $query): JsonResult
    {
        return new JsonResult([
            'success' => true,
            'events' => WhatsAppEvent::options(),
            'variables' => $this->templates->variables(),
            'templates' => $this->templates->templates(),
        ]);
    }
}
