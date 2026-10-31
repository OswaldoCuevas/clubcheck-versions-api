<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Services;

use App\Modules\Announcements\Contracts\IImageMimeDetector;

final class FileInfoImageMimeDetector implements IImageMimeDetector
{
    public function detect(string $path): string
    {
        return mime_content_type($path) ?: '';
    }
}
