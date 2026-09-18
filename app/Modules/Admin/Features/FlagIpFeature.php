<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Modules\Admin\Requests\FlagIpRequest;

final class FlagIpFeature
{
    public function __construct(
        private ?\Models\CustomerIpLogModel $ipLogs = null
    ) {
        $this->ipLogs ??= new \Models\CustomerIpLogModel();
    }

    public function handle(FlagIpRequest $request, string $appId): array
    {
        if (!$this->ipLogs->belongsToApp($request->id, $appId)) {
            throw new ForbiddenException('Registro de IP no pertenece a la app seleccionada');
        }

        if (!$this->ipLogs->setFlagged($request->id, $request->flagged, $request->reason)) {
            throw new NotFoundException('Registro de IP no encontrado');
        }

        return [
            'success' => true,
            'message' => $request->flagged ? 'IP marcada como sospechosa' : 'Marca de IP eliminada',
        ];
    }
}
