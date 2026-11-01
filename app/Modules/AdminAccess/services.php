<?php

declare(strict_types=1);

use App\Modules\AdminAccess\Contracts\IAdminAuthRepository;
use App\Modules\AdminAccess\Repositories\AdminAuthRepository;
use App\Modules\AdminAccess\Services\AdminAuthenticationService;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(IAdminAuthRepository::class, AdminAuthRepository::class);
    $container->bind(
        AdminAuthenticationService::class,
        static fn (Container $container): AdminAuthenticationService => new AdminAuthenticationService(
            $container->get(IAdminAuthRepository::class),
            require dirname(__DIR__, 3) . '/config/auth.php',
        ),
    );
};
