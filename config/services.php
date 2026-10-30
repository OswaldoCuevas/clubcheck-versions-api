<?php

declare(strict_types=1);

// Algunos scripts heredados cargan routes/web.php sin pasar por bootstrap.php.
require_once __DIR__ . '/../app/Core/Container.php';

use App\Modules\Announcements\Application\UploadImage\UploadAnnouncementImage;
use App\Modules\Announcements\Domain\AnnouncementImagePolicy;
use App\Modules\Announcements\Infrastructure\FileInfoImageMimeDetector;
use App\Modules\Announcements\Infrastructure\LocalAnnouncementImageStorage;
use Controllers\AdminAnnouncementController;
use Core\Container;

$container = new Container();

// Este es el punto de composición: aquí se conectan las interfaces y sus
// implementaciones concretas sin obligar al controlador a construirlas.
$container->singleton(AdminAnnouncementController::class, static function (): AdminAnnouncementController {
    $feature = new UploadAnnouncementImage(
        new FileInfoImageMimeDetector(),
        new AnnouncementImagePolicy(),
        new LocalAnnouncementImageStorage(
            dirname(__DIR__) . '/uploads/announcements',
            '/uploads/announcements',
        ),
    );

    return new AdminAnnouncementController($feature);
});

return $container;
