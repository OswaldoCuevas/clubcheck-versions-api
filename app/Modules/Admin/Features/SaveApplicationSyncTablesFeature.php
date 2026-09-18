<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\SaveApplicationSyncTablesRequest;
use Models\ApplicationModel;

final class SaveApplicationSyncTablesFeature
{
    public function __construct(private ?ApplicationModel $applications = null)
    {
        $this->applications ??= new ApplicationModel();
    }

    public function handle(SaveApplicationSyncTablesRequest $request): array
    {
        $this->applications->saveSyncTables($request->appId, $request->sync);

        return [
            'redirect' => '/admin/applications',
            'success' => 'Tablas de sincronizacion actualizadas.',
        ];
    }
}
