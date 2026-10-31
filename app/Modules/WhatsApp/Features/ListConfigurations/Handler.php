<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\ListConfigurations;

use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;

final readonly class Handler
{
    public function __construct(private IWhatsAppAdminRepository $configurations)
    {
    }

    public function handle(Query $query): JsonResult
    {
        $rows = $this->configurations->configurations();
        return new JsonResult([
            'success' => true,
            'count' => count($rows),
            'configurations' => $rows,
        ]);
    }
}
