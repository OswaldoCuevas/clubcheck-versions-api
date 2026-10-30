<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Http\Requests;

use App\Modules\Announcements\Application\UploadImage\UploadAnnouncementImageCommand;
use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use Closure;

final class UploadAnnouncementImageRequest
{
    /**
     * Función utilizada para comprobar que el archivo proviene de una subida HTTP real.
     * Se guarda como Closure para poder sustituir is_uploaded_file() durante las pruebas.
     */
    private Closure $isUploadedFile;

    /** @param array<string, mixed> $files */
    public function __construct(private readonly array $files, ?callable $isUploadedFile = null)
    {
        // En producción se usa is_uploaded_file(). Las pruebas pueden inyectar
        // una función controlada para no depender de una petición HTTP real.
        $this->isUploadedFile = Closure::fromCallable($isUploadedFile ?? 'is_uploaded_file');
    }

    public function toCommand(): UploadAnnouncementImageCommand
    {
        // Esta clase conoce la estructura de $_FILES y la traduce a un comando
        // sencillo que la capa de aplicación puede utilizar sin conocer HTTP.
        $file = $this->files['image'] ?? null;
        $temporaryPath = is_array($file) ? ($file['tmp_name'] ?? null) : null;

        // Una propiedad que contiene un Closure se ejecuta colocando () después.
        if (!is_string($temporaryPath) || $temporaryPath === '' || !(($this->isUploadedFile)($temporaryPath))) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementImageMissing);
        }

        return new UploadAnnouncementImageCommand(
            $temporaryPath,
            is_string($file['name'] ?? null) ? $file['name'] : '',
            (int) ($file['size'] ?? 0),
        );
    }
}
