<?php

namespace App\Middleware;

require_once __DIR__ . '/../Services/JwtService.php';
require_once __DIR__ . '/../Exceptions/UnauthorizedException.php';

use App\Exceptions\UnauthorizedException;
use App\Services\JwtService;

class DesktopJwtMiddleware
{
    private JwtService $jwtService;

    public function __construct(?JwtService $jwtService = null)
    {
        $this->jwtService = $jwtService ?? new JwtService();
    }

    public function handle(): bool
    {
        $token = $this->extractToken();

        if (!$token) {
            throw new UnauthorizedException('Token no proporcionado', 'TOKEN_MISSING');
        }

        $payload = $this->jwtService->validateToken($token);

        if (!$payload) {
            $info = $this->jwtService->getTokenInfo($token);
            if (($info['valid_format'] ?? false) && ($info['is_expired'] ?? false)) {
                throw new UnauthorizedException('Token expirado', 'TOKEN_EXPIRED');
            }

            throw new UnauthorizedException('Token invalido', 'TOKEN_INVALID');
        }

        if (empty($payload['customerId']) || empty($payload['name'])) {
            throw new UnauthorizedException('Token invalido para desktop', 'TOKEN_TYPE_INVALID');
        }

        $GLOBALS['desktop_jwt_payload'] = $payload;
        $GLOBALS['desktop_jwt_customer_id'] = (string) $payload['customerId'];
        $GLOBALS['desktop_jwt_customer_name'] = isset($payload['name']) ? (string) $payload['name'] : null;

        return true;
    }

    private function extractToken(): ?string
    {
        $authHeader = null;

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        }

        if ($authHeader && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return $_SERVER['HTTP_X_DESKTOP_JWT'] ?? $_SERVER['HTTP_X_ACCESS_TOKEN'] ?? null;
    }

    public static function getCurrentPayload(): ?array
    {
        return $GLOBALS['desktop_jwt_payload'] ?? null;
    }

    public static function getCurrentCustomerId(): ?string
    {
        return $GLOBALS['desktop_jwt_customer_id'] ?? null;
    }
}
