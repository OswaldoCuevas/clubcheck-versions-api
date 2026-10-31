<?php

declare(strict_types=1);
use App\Modules\Customers\Contracts\ICustomerRepository;
use App\Modules\Customers\Contracts\IDesktopSyncCoordinator;
use App\Modules\Customers\Contracts\IDesktopSyncRepository;
use App\Modules\Customers\Repositories\CustomerRepository;
use App\Modules\Customers\Repositories\DesktopSyncRepository;
use App\Modules\Customers\Services\DesktopSyncCoordinator;
use Core\Container;

return static function (Container $container): void {
    $container->bind(ICustomerRepository::class, CustomerRepository::class);
    $container->bind(IDesktopSyncRepository::class, DesktopSyncRepository::class);
    $container->bind(IDesktopSyncCoordinator::class, DesktopSyncCoordinator::class);
};
