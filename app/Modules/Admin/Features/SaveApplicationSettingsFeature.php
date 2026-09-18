<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\SaveApplicationSettingsRequest;
use Models\ApplicationModel;

final class SaveApplicationSettingsFeature
{
    public function __construct(private ?ApplicationModel $applications = null)
    {
        $this->applications ??= new ApplicationModel();
    }

    public function handle(SaveApplicationSettingsRequest $request): array
    {
        $this->applications->saveSettings($request->appId, $request->settings);

        return [
            'redirect' => '/admin/applications',
            'success' => 'Configuracion guardada.',
        ];
    }
}
