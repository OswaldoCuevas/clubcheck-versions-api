<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    public static function sendResult(JsonResult $result): never
    {
        self::send($result->data, $result->statusCode);
    }

    /** @param array<string, mixed> $data */
    public static function send(array $data, int $statusCode = 200): never
    {
        // Centraliza el contrato HTTP para no repetir estas instrucciones
        // dentro de cada controlador JSON.
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);

        // "never" es válido porque este método siempre termina la ejecución.
        exit;
    }
}
