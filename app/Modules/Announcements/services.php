<?php

declare(strict_types=1);

use App\Modules\Announcements\Contracts\IAnnouncementImageStorage;
use App\Modules\Announcements\Contracts\IAnnouncementRepository;
use App\Modules\Announcements\Contracts\ICurrentUserProvider;
use App\Modules\Announcements\Contracts\IImageMimeDetector;
use App\Modules\Announcements\Repositories\AnnouncementRepository;
use App\Modules\Announcements\Services\FileInfoImageMimeDetector;
use App\Modules\Announcements\Services\LocalAnnouncementImageStorage;
use App\Modules\Announcements\Services\SessionCurrentUserProvider;
use Core\Container;

return static function (Container $container): void
{
    // Lista de implementaciones elegidas para las interfaces del módulo.
    $container->bind(IAnnouncementRepository::class, AnnouncementRepository::class);
    $container->singleton(ICurrentUserProvider::class, SessionCurrentUserProvider::class);
    $container->singleton(IImageMimeDetector::class, FileInfoImageMimeDetector::class);

    // Esta implementación necesita valores escalares, por eso conserva una factory.
    $container->singleton(
        IAnnouncementImageStorage::class,
        static function (): IAnnouncementImageStorage
        {
            return new LocalAnnouncementImageStorage(
                dirname(__DIR__, 3) . '/uploads/announcements',
                '/uploads/announcements',
            );
        },
    );
};
