<?php

namespace App\Services;

/** Validation for the bounded binary exception to the text-only ISAPI queue. */
class IsapiPictureResult
{
    public const MAX_BYTES = 1572864;

    public static function validate(array $result): void
    {
        if (($result['metadata']['bodyEncoding'] ?? null) !== 'base64'
            || !isset($result['body']) || !is_string($result['body'])) {
            throw new \InvalidArgumentException('La captura debe incluir body Base64 y metadata.bodyEncoding=base64.');
        }
        $body = $result['body'];
        if ($body === '' || strlen($body) > 2097152) {
            throw new \InvalidArgumentException('La captura excede el limite de 1.5 MiB o esta vacia.');
        }
        $bytes = base64_decode($body, true);
        if ($bytes === false || base64_encode($bytes) !== $body || strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('La captura Base64 es invalida o demasiado grande.');
        }
        $type = $result['contentType'] ?? '';
        $signatureValid = ($type === 'image/jpeg' && substr($bytes, 0, 3) === "\xFF\xD8\xFF")
            || ($type === 'image/png' && substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n");
        $info = @getimagesizefromstring($bytes);
        if (!$signatureValid || !$info || ($info['mime'] ?? '') !== $type) {
            throw new \InvalidArgumentException('La captura debe ser una imagen JPEG o PNG valida y coincidir con contentType.');
        }
    }
}
