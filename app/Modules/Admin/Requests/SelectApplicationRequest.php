<?php

namespace App\Modules\Admin\Requests;

final class SelectApplicationRequest extends AdminRequest
{
    public readonly string $appId;
    public readonly string $redirect;

    public function __construct()
    {
        parent::__construct(['GET', 'POST']);

        $this->appId = trim((string) ($_POST['appId'] ?? $_GET['appId'] ?? ''));
        $redirect = trim((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? '/admin/dashboard'));
        $this->redirect = str_starts_with($redirect, '/admin') ? $redirect : '/admin/dashboard';
    }
}
