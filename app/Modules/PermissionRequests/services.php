<?php

declare(strict_types=1);

use App\Modules\PermissionRequests\Contracts\IPermissionRequestRepository;
use App\Modules\PermissionRequests\Repositories\PermissionRequestRepository;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(
        IPermissionRequestRepository::class,
        PermissionRequestRepository::class,
    );
};
