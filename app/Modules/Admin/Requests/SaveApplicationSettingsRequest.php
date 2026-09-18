<?php

namespace App\Modules\Admin\Requests;

final class SaveApplicationSettingsRequest extends AdminRequest
{
    public readonly string $appId;
    public readonly array $settings;

    public function __construct(string $selectedAppId)
    {
        parent::__construct(['POST']);

        $this->appId = trim((string) ($_POST['appId'] ?? $selectedAppId));
        $this->settings = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
    }
}
