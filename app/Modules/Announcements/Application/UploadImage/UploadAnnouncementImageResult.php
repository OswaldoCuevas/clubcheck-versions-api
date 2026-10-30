<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Application\UploadImage;

final readonly class UploadAnnouncementImageResult
{
    public function __construct(public string $publicPath)
    {
    }
}
