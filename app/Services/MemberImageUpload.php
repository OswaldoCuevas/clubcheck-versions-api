<?php

namespace App\Services;

use App\Exceptions\ApiException;

require_once __DIR__ . '/../Exceptions/ApiException.php';

class MemberImageUpload
{
    public const MAX_BYTES = 5242880;
    public const MAX_REQUEST_BYTES = self::MAX_BYTES + 16384;

    /** PHP 8.3 does not populate $_FILES for PUT. Parse a bounded multipart body. */
    public static function parse(string $contentType, string $body): array
    {
        if (strlen($body) > self::MAX_REQUEST_BYTES) {
            throw new ApiException('Imagen demasiado grande', 413, [], 'IMAGE_TOO_LARGE');
        }
        if (!preg_match('/^multipart\/form-data\s*;\s*boundary=(?:"([^"\r\n]{1,70})"|([^;\s]{1,70}))\s*$/i', $contentType, $match)) {
            throw new ApiException('Se requiere multipart/form-data', 415, [], 'IMAGE_MULTIPART_INVALID');
        }
        $boundary = $match[1] !== '' ? $match[1] : $match[2];
        $delimiter = '--' . $boundary;
        if (substr($body, 0, strlen($delimiter) + 2) !== $delimiter . "\r\n"
            || !in_array(substr($body, -strlen($delimiter) - 4), [$delimiter . "--\r\n"], true)) {
            // Also accept a closing boundary without its optional final CRLF.
            if (substr($body, 0, strlen($delimiter) + 2) !== $delimiter . "\r\n"
                || substr($body, -strlen($delimiter) - 2) !== $delimiter . '--') {
                throw new ApiException('Multipart incompleto', 422, [], 'IMAGE_MULTIPART_INVALID');
            }
        }
        $parts = explode("\r\n" . $delimiter, substr($body, strlen($delimiter) + 2));
        $closing = array_pop($parts);
        if (!in_array($closing, ['--', "--\r\n"], true) || count($parts) !== 3) {
            throw new ApiException('Se requieren archivo, hash y baseVersion', 422, [], 'IMAGE_FIELDS_INVALID');
        }
        $fields = [];
        foreach ($parts as $index => $part) {
            if ($index > 0) { $part = substr($part, 2); }
            $split = explode("\r\n\r\n", $part, 2);
            if (count($split) !== 2 || strlen($split[0]) > 4096
                || !preg_match('/(?:^|\r\n)Content-Disposition: form-data;\s*name="(archivo|hash|baseVersion)"(?:;[^\r\n]*)?(?:\r\n|$)/i', $split[0], $name)
                || isset($fields[$name[1]])) {
                throw new ApiException('Campos multipart invalidos', 422, [], 'IMAGE_FIELDS_INVALID');
            }
            $fields[$name[1]] = $split[1];
        }
        if (!isset($fields['archivo'], $fields['hash'], $fields['baseVersion'])
            || !preg_match('/^[a-fA-F0-9]{64}$/D', $fields['hash'])
            || !preg_match('/^(0|[1-9][0-9]{0,9})$/D', $fields['baseVersion'])
            || (int) $fields['baseVersion'] > 4294967294) {
            throw new ApiException('Hash o baseVersion invalidos', 422, [], 'IMAGE_FIELDS_INVALID');
        }
        $fields['hash'] = strtolower($fields['hash']);
        $fields['baseVersion'] = (int) $fields['baseVersion'];
        return $fields;
    }

    public static function validate(string $bytes, string $hash): string
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new ApiException('Imagen demasiado grande', 413, [], 'IMAGE_TOO_LARGE');
        }
        if (!hash_equals(hash('sha256', $bytes), $hash)) {
            throw new ApiException('Hash no coincide', 422, [], 'IMAGE_HASH_MISMATCH');
        }
        $info = @getimagesizefromstring($bytes);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!$info || !in_array($mime, ['image/jpeg', 'image/png'], true)
            || $info['mime'] !== $mime || $info[0] > 4096 || $info[1] > 4096
            || $info[0] * $info[1] > 16777216) {
            throw new ApiException('Solo JPEG/PNG validos de hasta 4096 x 4096', 415, [], 'IMAGE_FORMAT_INVALID');
        }
        if (!function_exists('imagecreatefromstring')) {
            throw new ApiException('Servicio de imagenes no disponible', 503, [], 'IMAGE_TEMPORARY_FAILURE');
        }
        $decoded = @imagecreatefromstring($bytes);
        if ($decoded === false) {
            throw new ApiException('Imagen corrupta', 415, [], 'IMAGE_FORMAT_INVALID');
        }
        imagedestroy($decoded);
        return $mime;
    }
}
