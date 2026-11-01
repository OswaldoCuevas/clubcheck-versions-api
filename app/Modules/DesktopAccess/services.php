<?php

declare(strict_types=1);

use App\Modules\DesktopAccess\Contracts\IDesktopLoginRepository;
use App\Modules\DesktopAccess\Repositories\DesktopLoginRepository;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(
        IDesktopLoginRepository::class,
        DesktopLoginRepository::class,
    );
};
