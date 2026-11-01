<?php

declare(strict_types=1);

namespace App\Http;

final class BinaryResponse
{
    public static function send(string $bytes, string $mime): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
        exit;
    }
}
