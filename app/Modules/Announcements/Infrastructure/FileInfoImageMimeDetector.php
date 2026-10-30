<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Infrastructure;

use App\Modules\Announcements\Domain\Contracts\IImageMimeDetector;

final class FileInfoImageMimeDetector implements IImageMimeDetector
{
    public function detect(string $path): string
    {
        return mime_content_type($path) ?: '';
    }
}
