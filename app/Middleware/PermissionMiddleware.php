<?php

declare(strict_types=1);

namespace Middleware;

use Core\UrlHelper;
use Models\UserModel;

final class PermissionMiddleware
{
    public function __construct(private ?UserModel $users = null)
    {
        $this->users ??= new UserModel();
    }

    public function handle(string $permission): void
    {
        if (!$this->users->isAuthenticated()) {
            $_SESSION['redirect_after_login'] = UrlHelper::getCurrentPath();
            header('Location: ' . UrlHelper::url('/login'));
            exit;
        }

        if (!$this->users->hasPermission($permission)) {
            http_response_code(403);
            echo '403 - Acceso denegado';
            exit;
        }
    }
}
