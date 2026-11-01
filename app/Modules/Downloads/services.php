<?php

declare(strict_types=1);

use App\Modules\Downloads\Contracts\IDownloadRepository;
use App\Modules\Downloads\Repositories\DownloadRepository;
use App\Modules\Downloads\Services\DownloadFileLocator;
use App\Modules\Downloads\Services\ReleaseUploadService;
use Core\Container;

return static function (Container $container): void
{
    $container->bind(IDownloadRepository::class, DownloadRepository::class);
    $container->bind(
        DownloadFileLocator::class,
        static fn (): DownloadFileLocator => new DownloadFileLocator(
            dirname(__DIR__, 3) . '/uploads',
        ),
    );
    $container->bind(
        ReleaseUploadService::class,
        static fn (Container $container): ReleaseUploadService => new ReleaseUploadService(
            $container->get(IDownloadRepository::class),
            dirname(__DIR__, 3) . '/uploads',
        ),
    );
};
