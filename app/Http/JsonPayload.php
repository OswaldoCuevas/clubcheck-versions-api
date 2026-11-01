<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;

final class JsonPayload
{
    /** @return array<string, mixed> */
    public static function parse(): array
    {
        $rawPayload = file_get_contents('php://input');

        if ($rawPayload === false || $rawPayload === '') {
            return [];
        }

        $payload = json_decode($rawPayload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ApiException::preservingResponse(
                ApiErrorCode::InvalidJsonPayload,
                [
                    'error' => 'Carga JSON inválida',
                    'message' => json_last_error_msg(),
                ],
            );
        }

        return is_array($payload) ? $payload : [];
    }
}
