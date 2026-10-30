<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Infrastructure;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\Announcements\Domain\Contracts\IAnnouncementImageStorage;
use Closure;

final class LocalAnnouncementImageStorage implements IAnnouncementImageStorage
{
    /** Función que mueve el archivo; normalmente es move_uploaded_file(). */
    private Closure $moveFile;

    public function __construct(
        private readonly string $directory,
        private readonly string $publicPrefix,
        ?callable $moveFile = null,
    ) {
        // Permitir otra función hace posible probar este adaptador con archivos
        // temporales normales, que PHP no considera subidas HTTP auténticas.
        $this->moveFile = Closure::fromCallable($moveFile ?? 'move_uploaded_file');
    }

    public function store(string $temporaryPath, string $extension): string
    {
        // El directorio se crea únicamente cuando realmente se guarda una imagen.
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true)) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementImageDirectoryCreationFailed);
        }

        // El nombre original no se utiliza: así se evitan colisiones y rutas
        // manipuladas como ../../archivo.php.
        $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $target = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!(($this->moveFile)($temporaryPath, $target))) {
            throw ApiException::fromErrorCode(ApiErrorCode::AnnouncementImageStorageFailed);
        }

        // Se devuelve una ruta pública, no la ruta física del servidor.
        return rtrim($this->publicPrefix, '/') . '/' . $filename;
    }
}
