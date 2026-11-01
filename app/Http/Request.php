<?php

declare(strict_types=1);

namespace App\Http;

use Core\UrlHelper;

final class Request
{
    /** @return array<string, mixed> */
    public static function queryParameters(): array
    {
        return $_GET;
    }

    /** @return array<string, mixed> */
    public static function formParameters(): array
    {
        return $_POST;
    }

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

    public static function clientIpAddress(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            $value = self::server($header);

            if ($value === null) {
                continue;
            }

            $ipAddress = trim(explode(',', $value)[0]);

            if (filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                return $ipAddress;
            }
        }

        return 'unknown';
    }

    public static function absoluteBaseUrl(): string
    {
        return UrlHelper::absoluteUrl('');
    }

    /** @return array<string, mixed> */
    public static function downloadParameters(): array
    {
        return [
            'info' => self::query('info'),
            'ipAddress' => self::clientIpAddress(),
            'userAgent' => self::server('HTTP_USER_AGENT'),
            'referrer' => self::server('HTTP_REFERER'),
        ];
    }

    public static function authorizationHeader(): ?string
    {
        return self::server('HTTP_AUTHORIZATION')
            ?? self::server('REDIRECT_HTTP_AUTHORIZATION');
    }
}
