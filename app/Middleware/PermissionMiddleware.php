<?php

namespace Middleware;

use Core\UrlHelper;

require_once __DIR__ . '/../Core/UrlHelper.php';

class PermissionMiddleware
{
    public function handle(string $permission): void
    {
        if (!class_exists('\Models\UserModel')) {
            require_once __DIR__ . '/../Models/UserModel.php';
        }

        $userModel = new \Models\UserModel();

        if (!$userModel->isAuthenticated()) {
            $_SESSION['redirect_after_login'] = UrlHelper::getCurrentPath();
            header('Location: ' . UrlHelper::url('/login'));
            exit;
        }

        if (!$userModel->hasPermission($permission)) {
            http_response_code(403);
            echo '403 - Acceso denegado';
            exit;
        }
    }
}
