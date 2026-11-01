<?php

declare(strict_types=1);

use App\Modules\UserPhotos\Contracts\IUserPhotoRepository;
use App\Modules\UserPhotos\Contracts\IUserPhotoStorage;
use App\Modules\UserPhotos\Repositories\UserPhotoRepository;
use App\Modules\UserPhotos\Services\LocalUserPhotoStorage;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(IUserPhotoRepository::class, UserPhotoRepository::class);
    $container->singleton(
        IUserPhotoStorage::class,
        static fn (): IUserPhotoStorage => new LocalUserPhotoStorage(
            dirname(__DIR__, 3) . '/uploads/user-photos',
        ),
    );
};
