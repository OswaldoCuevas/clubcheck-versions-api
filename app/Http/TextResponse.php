<?php

declare(strict_types=1);

namespace App\Http;

final class TextResponse
{
    public static function send(string $body, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }
}
