<?php

declare(strict_types=1);

namespace App\Http;

final class JsonPayload
{

    public static function Get():array
    {
        $body = json_decode(file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }
}
