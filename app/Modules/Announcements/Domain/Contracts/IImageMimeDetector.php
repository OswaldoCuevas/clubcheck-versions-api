<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Domain\Contracts;

interface IImageMimeDetector
{
    public function detect(string $path): string;
}
