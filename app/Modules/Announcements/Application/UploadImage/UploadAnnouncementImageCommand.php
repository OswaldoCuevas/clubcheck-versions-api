<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Application\UploadImage;

final readonly class UploadAnnouncementImageCommand
{
    public function __construct(
        public string $temporaryPath,
        public string $originalName,
        public int $size,
    ) {
    }
}
