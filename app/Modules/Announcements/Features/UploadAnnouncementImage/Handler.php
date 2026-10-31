<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\UploadAnnouncementImage;

use App\Modules\Announcements\Contracts\IAnnouncementImageStorage;
use App\Modules\Announcements\Contracts\IImageMimeDetector;
use LogicException;

/** El Mediator crea este Handler e inyecta sus dependencias al recibir Command. */
final readonly class Handler
{
    public function __construct(
        private IImageMimeDetector $mimeDetector,
        private ImagePolicy $policy,
        private IAnnouncementImageStorage $storage,
    ) {
    }

    /** Devuelve la ruta pública; este feature no necesita un Result DTO adicional. */
    public function handle(Command $command): string
    {
        $mime = $this->mimeDetector->detect($command->temporaryPath);
        $extension = $this->policy->extensionFor($mime, $command->size);

        $publicPath = $this->storage->store($command->temporaryPath, $extension);

        if ($publicPath === '') {
            throw new LogicException('IAnnouncementImageStorage devolvió una ruta vacía.');
        }

        return $publicPath;
    }
}
