<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ServiceException;
use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\UploadAnnouncementImageRequest;

final class UploadAnnouncementImageFeature
{
    public function handle(UploadAnnouncementImageRequest $request): array
    {
        $file = $request->file;
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = mime_content_type($file['tmp_name']) ?: '';

        if (!isset($allowed[$mime])) {
            throw new ValidationException('Formato de imagen no soportado');
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new ValidationException('La imagen no puede superar 5 MB');
        }

        $dir = __DIR__ . '/../../../../uploads/announcements';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new ServiceException('No se pudo crear el directorio de imagenes', 'UPLOAD_DIRECTORY_ERROR', 500);
        }

        $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            throw new ServiceException('No se pudo guardar la imagen', 'UPLOAD_WRITE_ERROR', 500);
        }

        return [
            'success' => true,
            'imageUrl' => app_url('/uploads/announcements/' . $filename),
        ];
    }
}
