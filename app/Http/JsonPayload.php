<?php

declare(strict_types=1);

namespace App\Http;

final class JsonPayload
{
    public static function Get(): array
    {
        $body = json_decode(file_get_contents('php://input'), true);

        return is_array($body) ? $body : [];
    }

    /**
     * Conserva el detalle del error de decodificación para endpoints cuyo
     * contrato HTTP distingue entre un objeto vacío y un JSON inválido.
     *
     * @return array{payload: array<string, mixed>, error: string|null}
     */
    public static function parse(): array
    {
        $rawPayload = file_get_contents('php://input');

        if ($rawPayload === false || $rawPayload === '') {
            return [
                'payload' => [],
                'error' => null,
            ];
        }

        $payload = json_decode($rawPayload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'payload' => [],
                'error' => json_last_error_msg(),
            ];
        }

        return [
            'payload' => is_array($payload) ? $payload : [],
            'error' => null,
        ];
    }
}
