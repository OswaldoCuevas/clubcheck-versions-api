<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Announcements\Contracts\IAnnouncementImageStorage;
use Closure;

final class LocalAnnouncementImageStorage implements IAnnouncementImageStorage
{
    private Closure $moveFile;

    public function __construct(
        private readonly string $directory,
        private readonly string $publicPrefix,
        ?callable $moveFile = null,
    ) {
        $this->moveFile = Closure::fromCallable($moveFile ?? 'move_uploaded_file');
    }

    public function store(string $temporaryPath, string $extension): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true)) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementImageDirectoryCreationFailed);
        }

        // El nombre original no se usa: evita colisiones y rutas manipuladas.
        $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $target = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!(($this->moveFile)($temporaryPath, $target))) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementImageStorageFailed);
        }

        return rtrim($this->publicPrefix, '/') . '/' . $filename;
    }
}
