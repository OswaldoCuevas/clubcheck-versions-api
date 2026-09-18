<?php

namespace App\Modules\Admin\Requests;

final class SaveApplicationSyncTablesRequest extends AdminRequest
{
    public readonly string $appId;
    public readonly array $sync;

    public function __construct(string $selectedAppId)
    {
        parent::__construct(['POST']);

        $this->appId = trim((string) ($_POST['appId'] ?? $selectedAppId));
        $this->sync = is_array($_POST['sync'] ?? null) ? $_POST['sync'] : [];
    }
}
