<?php

declare(strict_types=1);

namespace App\Modules\UserPhotos\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use RuntimeException;

final class UserPhotoProcessor
{
    public const MAX_BYTES = 5242880;

    public function process(array $fields, array $file): array
    {
        $version = $fields['version'] ?? null;
        $hash = $fields['sha256'] ?? null;
        if (!is_string($version)
            || !preg_match('/^[1-9][0-9]*$/D', $version)
            || strlen($version) > 19
            || (strlen($version) === 19 && strcmp($version, (string) PHP_INT_MAX) > 0)
            || !is_string($hash)
            || !preg_match('/^[a-fA-F0-9]{64}$/D', $hash)) {
            $this->error(ApiErrorCode::UserPhotoInvalid, 'version o sha256 invalidos');
        }
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $this->error(ApiErrorCode::UserPhotoTooLarge, 'La imagen excede el limite');
        }
        $temporaryPath = $file['tmp_name'] ?? null;
        if ($error !== UPLOAD_ERR_OK || !is_string($temporaryPath) || !is_file($temporaryPath)) {
            $this->error(
                ApiErrorCode::UserPhotoInvalid,
                'image es obligatoria y debe estar completa',
            );
        }
        $size = filesize($temporaryPath);
        if ($size > self::MAX_BYTES) {
            $this->error(ApiErrorCode::UserPhotoTooLarge, 'La imagen excede 5 MiB');
        }
        $info = @getimagesize($temporaryPath);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            $this->error(ApiErrorCode::UserPhotoUnsupported, 'Solo se acepta JPEG o PNG real');
        }
        if ($info[0] > 4096 || $info[1] > 4096) {
            $this->error(ApiErrorCode::UserPhotoInvalid, 'Las dimensiones exceden 4096 pixeles');
        }
        $hash = strtolower($hash);
        if (!hash_equals($hash, (string) hash_file('sha256', $temporaryPath))) {
            $this->error(ApiErrorCode::UserPhotoInvalid, 'sha256 no coincide con image');
        }
        $bytes = file_get_contents($temporaryPath);
        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            $this->error(ApiErrorCode::UserPhotoUnsupported, 'Imagen corrupta');
        }
        $ratio = min(1, 256 / $info[0], 256 / $info[1]);
        $width = max(1, (int) round($info[0] * $ratio));
        $height = max(1, (int) round($info[1] * $ratio));
        $thumbnailImage = imagecreatetruecolor($width, $height);
        imagealphablending($thumbnailImage, false);
        imagesavealpha($thumbnailImage, true);
        imagecopyresampled(
            $thumbnailImage,
            $image,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $info[0],
            $info[1],
        );
        ob_start();
        try {
            if (!imagepng($thumbnailImage)) {
                throw new RuntimeException('No se pudo generar miniatura');
            }
            $thumbnail = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        return [
            'version' => (int) $version,
            'sha256' => $hash,
            'mime' => $info['mime'],
            'size' => $size,
            'bytes' => $bytes,
            'thumbnail' => $thumbnail,
        ];
    }

    private function error(ApiErrorCode $code, string $message): never
    {
        throw ApiException::preservingResponse($code, ['error' => $message]);
    }
}
