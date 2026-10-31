<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\DeleteTemplate;

use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;

final readonly class Handler
{
    public function __construct(private IWhatsAppAdminRepository $templates)
    {
    }

    public function handle(Command $command): JsonResult
    {
        return new JsonResult($this->templates->deleteTemplate($command->id));
    }
}
