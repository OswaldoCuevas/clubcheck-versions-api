<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Application\UploadImage;

use App\Modules\Announcements\Domain\AnnouncementImagePolicy;
use App\Modules\Announcements\Domain\Contracts\IAnnouncementImageStorage;
use App\Modules\Announcements\Domain\Contracts\IImageMimeDetector;

final readonly class UploadAnnouncementImage
{
    public function __construct(
        private IImageMimeDetector $mimeDetector,
        private AnnouncementImagePolicy $policy,
        private IAnnouncementImageStorage $storage,
    ) {
    }

    public function execute(UploadAnnouncementImageCommand $command): UploadAnnouncementImageResult
    {
        // 1. Detectamos el tipo real por el contenido, no por la extensión enviada.
        $mime = $this->mimeDetector->detect($command->temporaryPath);

        // 2. La política de dominio valida formato/tamaño y decide la extensión segura.
        $extension = $this->policy->extensionFor($mime, $command->size);

        // 3. El adaptador de almacenamiento mueve el archivo y devuelve su ruta pública.
        $publicPath = $this->storage->store($command->temporaryPath, $extension);

        return new UploadAnnouncementImageResult($publicPath);
    }
}
