<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Features\UploadAnnouncementImage;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;

/** Reglas propias del feature de subida de imágenes. */
final class ImagePolicy
{
    public const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function extensionFor(string $mime, int $size): string
    {
        // Nunca confiamos en el nombre o la extensión enviados por el cliente.
        $extension = self::ALLOWED_MIME_TYPES[$mime] ?? null;
        if ($extension === null) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementImageUnsupportedFormat);
        }

        if ($size > self::MAX_SIZE_BYTES) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementImageTooLarge);
        }

        return $extension;
    }
}
