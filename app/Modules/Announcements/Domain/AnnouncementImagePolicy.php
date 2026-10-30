<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Domain;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;

final class AnnouncementImagePolicy
{
    // El límite pertenece a la regla de negocio y no al controlador HTTP.
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
        // La extensión se obtiene del MIME detectado. Nunca confiamos en el
        // nombre o la extensión original proporcionada por el cliente.
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
