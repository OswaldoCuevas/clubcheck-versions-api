<?php

declare(strict_types=1);

use App\Modules\Isapi\Contracts\IIsapiCommandCatalog;
use App\Modules\Isapi\Contracts\IIsapiCommandRepository;
use App\Modules\Isapi\Repositories\IsapiCommandRepository;
use App\Modules\Isapi\Services\IsapiCommandCatalog;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(IIsapiCommandRepository::class, IsapiCommandRepository::class);
    $container->bind(IIsapiCommandCatalog::class, IsapiCommandCatalog::class);
};
