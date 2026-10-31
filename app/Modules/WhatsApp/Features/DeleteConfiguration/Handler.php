<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Features\DeleteConfiguration;

use App\Http\JsonResult;
use App\Modules\WhatsApp\Contracts\IWhatsAppAdminRepository;

final readonly class Handler
{
    public function __construct(private IWhatsAppAdminRepository $configurations)
    {
    }

    public function handle(Command $command): JsonResult
    {
        if ($command->id === '') {
            return new JsonResult(['error' => 'ID es requerido'], 422);
        }

        $result = $this->configurations->deleteConfiguration($command->id);
        if (!$result['success']) {
            return new JsonResult(['error' => $result['error']], 404);
        }

        return new JsonResult([
            'success' => true,
            'message' => 'Configuración eliminada correctamente',
        ]);
    }
}
