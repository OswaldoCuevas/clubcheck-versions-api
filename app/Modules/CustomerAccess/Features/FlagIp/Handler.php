<?php

declare(strict_types=1);

namespace App\Modules\CustomerAccess\Features\FlagIp;

use App\Http\JsonResult;
use App\Modules\CustomerAccess\Contracts\ICustomerAccessRepository;

final readonly class Handler
{
    public function __construct(private ICustomerAccessRepository $ips)
    {
    }
    public function handle(Command $command): JsonResult
    {
        $flagged = isset($command->payload['flagged']) ? (bool)$command->payload['flagged'] : true;
        $reason = isset($command->payload['reason']) ? trim((string)$command->payload['reason']) : null;
        if (!$this->ips->flagIp($command->id, $flagged, $reason)) {
            return new JsonResult(
                [
                    'success' => false,
                    'error' => 'Registro de IP no encontrado',
                ],
                404,
            );
        }

        return new JsonResult([
            'success' => true,
            'message' => $flagged ? 'IP marcada como sospechosa' : 'Marca de IP eliminada',
        ]);
    }
}
