<?php

namespace App\Middleware;

require_once __DIR__ . '/../Services/CustomerJwtService.php';
require_once __DIR__ . '/../Exceptions/UnauthorizedException.php';

use App\Services\CustomerJwtService;
use App\Exceptions\UnauthorizedException;

/**
 * Middleware para autenticación JWT de clientes (aplicación de escritorio)
 * 
 * Valida que las peticiones contengan un token JWT válido de cliente.
 * A diferencia del JwtMiddleware genérico, este middleware:
 * - Verifica que el JWT coincida con el almacenado en la BD
 * - Valida que el token de máquina (GUID) coincida
 * - Registra las IPs de acceso para monitoreo
 * 
 * El token debe enviarse en el header:
 *   Authorization: Bearer <token>
 * 
 * Uso en rutas:
 *   $router->get('/api/customers/protected', 'Controller', 'method', ['customer_jwt']);
 */
class CustomerJwtMiddleware
{
    private CustomerJwtService $customerJwtService;
    private ?array $currentPayload = null;
    private ?string $currentCustomerId = null;
    private ?array $validationResult = null;

    public function __construct(?CustomerJwtService $customerJwtService = null)
    {
        $this->customerJwtService = $customerJwtService ?? new CustomerJwtService();
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

        // Obtener información del request para logging
        $ipAddress = $this->getClientIp();
        $deviceName = $_SERVER['HTTP_X_DEVICE_NAME'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Validar el token contra la BD
        $result = $this->customerJwtService->validateCustomerToken(
            $token,
            $ipAddress,
            $deviceName,
            $userAgent
        );

        $this->validationResult = $result;

        if (!$result['valid']) {
            $errorMessages = [
                'TOKEN_EXPIRED' => 'Token expirado',
                'TOKEN_INVALID' => 'Token inválido',
                'TOKEN_TYPE_INVALID' => 'Tipo de token inválido',
                'TOKEN_INCOMPLETE' => 'Token incompleto',
                'CUSTOMER_NOT_FOUND' => 'Cliente no encontrado',
                'CUSTOMER_INACTIVE' => 'Cliente inactivo',
                'TOKEN_NOT_CURRENT' => 'Token no es el actual',
                'MACHINE_TOKEN_MISMATCH' => 'Token de máquina no coincide',
            ];

            $message = $errorMessages[$result['errorCode']] ?? 'Token inválido';
            throw new UnauthorizedException($message, $result['errorCode']);
        }

        // Guardar información para uso posterior
        $this->currentPayload = $result['payload'];
        $this->currentCustomerId = $result['customerId'];
        
        // Hacer disponible globalmente
        $GLOBALS['customer_jwt_payload'] = $result['payload'];
        $GLOBALS['customer_jwt_customer_id'] = $result['customerId'];
        $GLOBALS['customer_jwt_machine_token'] = $result['machineToken'];

        return true;
    }

    /**
     * Obtiene el payload del token actual
     */
    public function getPayload(): ?array
    {
        return $this->currentPayload;
    }

    /**
     * Obtiene el ID del cliente autenticado
     */
    public function getCustomerId(): ?string
    {
        return $this->currentCustomerId;
    }

    /**
     * Obtiene el resultado completo de la validación
     */
    public function getValidationResult(): ?array
    {
        return $this->validationResult;
    }

    /**
     * Extrae el token del header Authorization
     */
    private function extractToken(): ?string
    {
        $authHeader = null;
        
        // 1. Intentar con apache_request_headers() o getallheaders() primero
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }
        
        // 2. Fallback a $_SERVER (múltiples variantes)
        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] 
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
                ?? null;
        }
        
        // 3. Extraer el token del formato "Bearer <token>"
        if ($authHeader && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // 4. Headers alternativos para compatibilidad
        if (isset($_SERVER['HTTP_X_ACCESS_TOKEN'])) {
            return $_SERVER['HTTP_X_ACCESS_TOKEN'];
        }

        if (isset($_SERVER['HTTP_X_CUSTOMER_JWT'])) {
            return $_SERVER['HTTP_X_CUSTOMER_JWT'];
        }

        return null;
    }

    /**
     * Obtiene la IP del cliente
     */
    private function getClientIp(): ?string
    {
        // Verificar headers de proxy (Cloudflare, etc.)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy estándar
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR',               // Directo
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Si hay múltiples IPs (X-Forwarded-For), tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validar que sea una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    // ===== Métodos estáticos para acceso global =====

    /**
     * Obtiene el payload del token actual desde cualquier lugar
     */
    public static function getCurrentPayload(): ?array
    {
        return $GLOBALS['customer_jwt_payload'] ?? null;
    }

    /**
     * Obtiene el ID del cliente actual desde cualquier lugar
     */
    public static function getCurrentCustomerId(): ?string
    {
        return $GLOBALS['customer_jwt_customer_id'] ?? null;
    }

    /**
     * Obtiene el token de máquina actual desde cualquier lugar
     */
    public static function getCurrentMachineToken(): ?string
    {
        return $GLOBALS['customer_jwt_machine_token'] ?? null;
    }

    /**
     * Obtiene un valor específico del payload actual
     */
    public static function get(string $key, $default = null)
    {
        $payload = self::getCurrentPayload();
        return $payload[$key] ?? $default;
    }

    /**
     * Método estático para validar token sin instanciar el middleware
     */
    public static function validate(string $token): array
    {
        $service = new CustomerJwtService();
        return $service->validateCustomerToken($token);
    }
}
