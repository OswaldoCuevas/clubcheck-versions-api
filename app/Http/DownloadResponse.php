<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class DownloadResponse
{
    public static function send(DownloadResult $result): never
    {
        if ($result->information !== null) {
            JsonResponse::send($result->information);
        }

        $size = filesize($result->path);

        if ($size === false) {
            throw new RuntimeException('No se pudo obtener el tamaño del archivo.');
        }

        $handle = fopen($result->path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('No se puede leer el archivo.');
        }

        header('Content-Type: ' . $result->contentType);
        header('Content-Disposition: attachment; filename="' . $result->filename . '"');
        header('Content-Length: ' . $size);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Access-Control-Allow-Origin: *');

        while (!feof($handle) && connection_status() === CONNECTION_NORMAL) {
            $buffer = fread($handle, 8192);

            if ($buffer === false) {
                fclose($handle);
                throw new RuntimeException('No se puede leer el archivo.');
            }

            echo $buffer;
            flush();
        }

        fclose($handle);
        exit;
    }
}
