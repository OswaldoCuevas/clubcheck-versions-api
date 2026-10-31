<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\UploadAnnouncementImage;

/** Datos de entrada de la operación; no contiene servicios ni lógica. */
final readonly class Command
{
    public function __construct(
        public string $temporaryPath,
        public string $originalName,
        public int $size,
    ) {
    }
}
