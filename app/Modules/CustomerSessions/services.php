<?php

declare(strict_types=1);

use App\Modules\CustomerSessions\Contracts\ICustomerSessionRepository;
use App\Modules\CustomerSessions\Repositories\CustomerSessionRepository;
use App\Modules\CustomerSessions\Services\CustomerSessionConfiguration;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(
        ICustomerSessionRepository::class,
        CustomerSessionRepository::class,
    );
    $container->singleton(
        CustomerSessionConfiguration::class,
        static function (): CustomerSessionConfiguration
        {
            $sessionConfiguration = config('customerSessions', []);

            return new CustomerSessionConfiguration(
                (int) ($sessionConfiguration['heartbeat_interval'] ?? 60),
                (int) ($sessionConfiguration['grace_period'] ?? 180),
                (int) ($sessionConfiguration['max_metadata_size'] ?? 2048),
            );
        },
    );
};
