<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    public static function query(string $key): ?string
    {
        $value = $_GET[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public static function server(string $key): ?string
    {
        $value = $_SERVER[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public static function ipAddress(): ?string
    {
        return self::server('REMOTE_ADDR');
    }

    public static function authorizationHeader(): ?string
    {
        return self::server('HTTP_AUTHORIZATION')
            ?? self::server('REDIRECT_HTTP_AUTHORIZATION');
    }
}
