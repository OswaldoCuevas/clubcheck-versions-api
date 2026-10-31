<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Controllers\Requests;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Announcements\Features\UploadAnnouncementImage\Command;
use Closure;

final class UploadAnnouncementImageRequest
{
    private Closure $isUploadedFile;

    /** @param array<string, mixed> $files */
    public function __construct(private readonly array $files, ?callable $isUploadedFile = null)
    {
        // La función inyectable permite probar sin una subida HTTP real.
        $this->isUploadedFile = Closure::fromCallable($isUploadedFile ?? 'is_uploaded_file');
    }

    public function toCommand(): Command
    {
        $file = $this->files['image'] ?? null;
        $temporaryPath = is_array($file) ? ($file['tmp_name'] ?? null) : null;

        if (!is_string($temporaryPath) || $temporaryPath === '' || !(($this->isUploadedFile)($temporaryPath))) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementImageMissing);
        }

        return new Command(
            $temporaryPath,
            is_string($file['name'] ?? null) ? $file['name'] : '',
            (int) ($file['size'] ?? 0),
        );
    }
}
