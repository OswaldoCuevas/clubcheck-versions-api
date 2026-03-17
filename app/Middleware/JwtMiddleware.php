<?php

namespace App\Middleware;

use App\Services\JwtService;
use App\Exceptions\UnauthorizedException;

/**
 * Middleware para autenticación JWT
 * 
 * Valida que las peticiones contengan un token JWT válido en el header Authorization.
 * 
 * El token debe enviarse en el header:
 *   Authorization: Bearer <token>
 * 
 * Uso en rutas:
 *   $router->get('/api/protected', 'Controller', 'method', ['jwt']);
 */
class JwtMiddleware
{
    private JwtService $jwtService;
    private ?array $currentPayload = null;

    public function __construct(?JwtService $jwtService = null)
    {
        $this->jwtService = $jwtService ?? new JwtService();
    }

    /**
     * Ejecuta el middleware
     * 
     * @throws UnauthorizedException Si el token no es válido
     * @return bool True si el token es válido
     */
    public function handle(): bool
    {
        $token = $this->extractToken();

        if (!$token) {
            throw new UnauthorizedException('Token no proporcionado', 'TOKEN_MISSING');
        }

        $payload = $this->jwtService->validateToken($token);

        if (!$payload) {
            // Verificar si el token expiró para dar mejor mensaje
            $info = $this->jwtService->getTokenInfo($token);
            
            if ($info['valid_format'] && $info['is_expired']) {
                throw new UnauthorizedException('Token expirado', 'TOKEN_EXPIRED');
            }

            throw new UnauthorizedException('Token inválido', 'TOKEN_INVALID');
        }

        // Guardar payload para uso posterior
        $this->currentPayload = $payload;
        
        // Hacer disponible el payload globalmente
        $GLOBALS['jwt_payload'] = $payload;

        return true;
    }

    /**
     * Obtiene el payload del token actual
     * 
     * @return array|null
     */
    public function getPayload(): ?array
    {
        return $this->currentPayload;
    }

    /**
     * Extrae el token del header Authorization
     * 
     * Soporta:
     *   - Authorization: Bearer <token>
     *   - X-Access-Token: <token>
     *   - Query param: ?token=<token>
     * 
     * @return string|null Token o null si no se encuentra
     */
    private function extractToken(): ?string
    {
        // Intentar obtener del header Authorization
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

        if ($authHeader && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Intentar del header X-Access-Token
        if (isset($_SERVER['HTTP_X_ACCESS_TOKEN'])) {
            return $_SERVER['HTTP_X_ACCESS_TOKEN'];
        }

        // Intentar del query param (no recomendado para producción)
        if (isset($_GET['token'])) {
            return $_GET['token'];
        }

        return null;
    }

    /**
     * Método estático para validar token sin instanciar el middleware
     * Útil para validación rápida en controladores
     * 
     * @param string $token
     * @return array|null Payload o null si es inválido
     */
    public static function validate(string $token): ?array
    {
        $service = new JwtService();
        return $service->validateToken($token);
    }

    /**
     * Obtiene el payload del token actual desde cualquier lugar
     * 
     * @return array|null
     */
    public static function getCurrentPayload(): ?array
    {
        return $GLOBALS['jwt_payload'] ?? null;
    }

    /**
     * Obtiene un valor específico del payload actual
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $payload = self::getCurrentPayload();
        return $payload[$key] ?? $default;
    }

    /**
     * Verifica si el usuario actual tiene un rol específico
     * 
     * @param string|array $roles Rol o array de roles permitidos
     * @return bool
     */
    public static function hasRole($roles): bool
    {
        $payload = self::getCurrentPayload();
        
        if (!$payload || !isset($payload['role'])) {
            return false;
        }

        $userRole = $payload['role'];
        $allowedRoles = is_array($roles) ? $roles : [$roles];

        return in_array($userRole, $allowedRoles);
    }
}
