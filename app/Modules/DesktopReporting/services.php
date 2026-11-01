<?php

declare(strict_types=1);

use App\Modules\DesktopReporting\Contracts\IDesktopReportingRepository;
use App\Modules\DesktopReporting\Contracts\IDesktopUserProfileRepository;
use App\Modules\DesktopReporting\Repositories\DesktopReportingRepository;
use App\Modules\DesktopReporting\Repositories\DesktopUserProfileRepository;
use App\Infrastructure\Database\IDatabaseConnection;
use App\Services\MembershipSalesService;
use App\Services\UserPhotoService;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(
        IDesktopReportingRepository::class,
        DesktopReportingRepository::class,
    );
    $container->bind(
        IDesktopUserProfileRepository::class,
        DesktopUserProfileRepository::class,
    );
    $container->bind(
        MembershipSalesService::class,
        static fn (Container $container): MembershipSalesService => new MembershipSalesService(
            $container->get(IDatabaseConnection::class),
        ),
    );
    $container->bind(
        UserPhotoService::class,
        static fn (Container $container): UserPhotoService => new UserPhotoService(
            $container->get(IDatabaseConnection::class),
        ),
    );
};
