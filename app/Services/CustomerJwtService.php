<?php

namespace App\Services;

require_once __DIR__ . '/JwtService.php';
require_once __DIR__ . '/../Models/CustomerIpLogModel.php';

use Models\CustomerIpLogModel;

/**
 * Servicio especializado para JWT de clientes (aplicación de escritorio)
 * 
 * Extiende la funcionalidad del JwtService base para:
 * - Crear tokens que incluyan customerId y token (GUID de la máquina)
 * - Validar tokens contra la base de datos
 * - Registrar y monitorear IPs de acceso
 * 
 * Uso:
 *   $customerJwt = new CustomerJwtService();
 *   
 *   // Crear token para un cliente
 *   $jwt = $customerJwt->createCustomerToken($customerId, $machineToken);
 *   
 *   // Validar token (verifica firma Y coincidencia en BD)
 *   $result = $customerJwt->validateCustomerToken($jwt);
 *   if ($result['valid']) {
 *       echo "Customer: " . $result['customerId'];
 *   }
 */
class CustomerJwtService
{
    private JwtService $jwtService;
    private $db;
    private CustomerIpLogModel $ipLogModel;
    
    /**
     * Duración por defecto del token: 30 días
     */
    private const DEFAULT_EXPIRATION = 2592000; // 30 * 24 * 60 * 60

    public function __construct(?JwtService $jwtService = null)
    {
        $this->jwtService = $jwtService ?? new JwtService();
        $this->jwtService->setDefaultExpiration(self::DEFAULT_EXPIRATION);
        
        require_once __DIR__ . '/../../utils/database.php';
        $this->db = new \Database();
        
        $this->ipLogModel = new CustomerIpLogModel();
    }

    /**
     * Crea un nuevo token JWT para un cliente
     * 
     * @param string $customerId ID del cliente
     * @param string $machineToken Token de la máquina (GUID)
     * @param int|null $expiresIn Tiempo de expiración en segundos (default: 30 días, 0 = sin caducidad)
     * @return array ['jwt' => string, 'expiresAt' => DateTime|null, 'expiresIn' => int]
     * @throws \InvalidArgumentException Si faltan parámetros
     * @throws \RuntimeException Si el cliente no existe
     */
    public function createCustomerToken(string $customerId, string $machineToken, ?int $expiresIn = null): array
    {
        $customerId = trim($customerId);
        $machineToken = trim($machineToken);
        
        if ($customerId === '' || $machineToken === '') {
            throw new \InvalidArgumentException('customerId and machineToken are required');
        }
        
        // Verificar que el cliente existe
        $customer = $this->db->fetchOne(
            'SELECT Id, Token, IsActive FROM Customers WHERE Id = ?',
            [$customerId]
        );
        
        if (!$customer) {
            throw new \RuntimeException('customer_not_found');
        }
        
        if (!$customer['IsActive']) {
            throw new \RuntimeException('customer_inactive');
        }
        
        // Verificar que el token de máquina coincida
        if ($customer['Token'] !== $machineToken) {
            throw new \RuntimeException('machine_token_mismatch');
        }
        
        // Si $expiresIn es null, usar default. Si es 0, sin caducidad
        $expiration = $expiresIn ?? self::DEFAULT_EXPIRATION;
        $expiresAt = ($expiration === 0) ? null : (time() + $expiration);
        
        // Crear el payload del JWT
        $payload = [
            'cid' => $customerId,           // Customer ID
            'mkt' => $machineToken,         // Machine Token (GUID)
            'typ' => 'customer',            // Tipo de token
        ];
        
        // Generar el JWT
        $jwt = $this->jwtService->createToken($payload, $expiration);
        
        // Almacenar el JWT en la base de datos
        $this->db->update(
            'Customers',
            [
                'TokenJwt' => $jwt,
                'TokenJwtCreatedAt' => date('Y-m-d H:i:s'),
                'TokenJwtExpiresAt' => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : null,
            ],
            'Id = ?',
            [$customerId]
        );
        
        return [
            'jwt' => $jwt,
            'expiresAt' => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : null,
            'expiresIn' => $expiration,
        ];
    }

    /**
     * Valida un token JWT de cliente
     * 
     * Verifica:
     * 1. Que la firma del JWT sea válida
     * 2. Que el token no haya expirado
     * 3. Que el JWT coincida con el almacenado en la BD para el customerId
     * 4. Que el token de máquina coincida con el del cliente
     * 
     * @param string $jwt Token JWT a validar
     * @param string|null $ipAddress IP de donde proviene la petición (para logging)
     * @param string|null $deviceName Nombre del dispositivo
     * @param string|null $userAgent User-Agent del cliente
     * @return array ['valid' => bool, 'customerId' => string|null, 'error' => string|null, 'payload' => array|null]
     */
    public function validateCustomerToken(
        string $jwt, 
        ?string $ipAddress = null, 
        ?string $deviceName = null,
        ?string $userAgent = null
    ): array {
        // 1. Validar la firma y expiración del JWT
        $payload = $this->jwtService->validateToken($jwt);
        
        if (!$payload) {
            // Verificar si expiró para dar mejor mensaje
            $info = $this->jwtService->getTokenInfo($jwt);
            
            if ($info['valid_format'] && $info['is_expired']) {
                return [
                    'valid' => false,
                    'customerId' => $info['payload']['cid'] ?? null,
                    'error' => 'token_expired',
                    'errorCode' => 'TOKEN_EXPIRED',
                    'payload' => null,
                ];
            }
            
            return [
                'valid' => false,
                'customerId' => null,
                'error' => 'invalid_token',
                'errorCode' => 'TOKEN_INVALID',
                'payload' => null,
            ];
        }
        
        // 2. Verificar que sea un token de tipo customer
        if (($payload['typ'] ?? '') !== 'customer') {
            return [
                'valid' => false,
                'customerId' => null,
                'error' => 'invalid_token_type',
                'errorCode' => 'TOKEN_TYPE_INVALID',
                'payload' => null,
            ];
        }
        
        $customerId = $payload['cid'] ?? null;
        $machineToken = $payload['mkt'] ?? null;
        
        if (!$customerId || !$machineToken) {
            return [
                'valid' => false,
                'customerId' => null,
                'error' => 'incomplete_token_payload',
                'errorCode' => 'TOKEN_INCOMPLETE',
                'payload' => null,
            ];
        }
        
        // 3. Verificar contra la base de datos
        $customer = $this->db->fetchOne(
            'SELECT Id, Token, TokenJwt, IsActive FROM Customers WHERE Id = ?',
            [$customerId]
        );
        
        if (!$customer) {
            return [
                'valid' => false,
                'customerId' => $customerId,
                'error' => 'customer_not_found',
                'errorCode' => 'CUSTOMER_NOT_FOUND',
                'payload' => null,
            ];
        }
        
        if (!$customer['IsActive']) {
            return [
                'valid' => false,
                'customerId' => $customerId,
                'error' => 'customer_inactive',
                'errorCode' => 'CUSTOMER_INACTIVE',
                'payload' => null,
            ];
        }
        
        // 4. Verificar que el JWT coincida con el almacenado
        if ($customer['TokenJwt'] !== $jwt) {
            return [
                'valid' => false,
                'customerId' => $customerId,
                'error' => 'token_not_current',
                'errorCode' => 'TOKEN_NOT_CURRENT',
                'payload' => null,
            ];
        }
        
        // 5. Verificar que el token de máquina coincida
        if ($customer['Token'] !== $machineToken) {
            return [
                'valid' => false,
                'customerId' => $customerId,
                'error' => 'machine_token_mismatch',
                'errorCode' => 'MACHINE_TOKEN_MISMATCH',
                'payload' => null,
            ];
        }
        
        // 6. Registrar el acceso si tenemos IP (solo si es diferente a la última)
        if ($ipAddress) {
            try {
                $this->ipLogModel->logAccess($customerId, $ipAddress, $deviceName, $userAgent);
            } catch (\Throwable $e) {
                // No fallar la validación si el logging de IP falla
                error_log("CustomerJwtService: Failed to log IP access: " . $e->getMessage());
            }
        }
        
        // 7. Actualizar LastSeen del cliente
        $this->db->update(
            'Customers',
            ['LastSeen' => date('Y-m-d H:i:s')],
            'Id = ?',
            [$customerId]
        );
        
        return [
            'valid' => true,
            'customerId' => $customerId,
            'machineToken' => $machineToken,
            'error' => null,
            'errorCode' => null,
            'payload' => $payload,
        ];
    }

    /**
     * Revoca (invalida) el token JWT de un cliente
     * 
     * @param string $customerId ID del cliente
     * @return bool True si se revocó exitosamente
     */
    public function revokeCustomerToken(string $customerId): bool
    {
        $result = $this->db->update(
            'Customers',
            [
                'TokenJwt' => null,
                'TokenJwtCreatedAt' => null,
                'TokenJwtExpiresAt' => null,
            ],
            'Id = ?',
            [$customerId]
        );
        
        return $result;
    }

    /**
     * Renueva el token JWT de un cliente
     * 
     * @param string $customerId ID del cliente
     * @param int|null $expiresIn Nueva duración en segundos
     * @return array|null Nuevo token o null si no se pudo renovar
     */
    public function renewCustomerToken(string $customerId, ?int $expiresIn = null): ?array
    {
        $customer = $this->db->fetchOne(
            'SELECT Id, Token, IsActive FROM Customers WHERE Id = ?',
            [$customerId]
        );
        
        if (!$customer || !$customer['IsActive'] || !$customer['Token']) {
            return null;
        }
        
        return $this->createCustomerToken($customerId, $customer['Token'], $expiresIn);
    }

    /**
     * Obtiene información del token de un cliente
     * 
     * @param string $customerId ID del cliente
     * @return array|null Información del token o null si no existe
     */
    public function getCustomerTokenInfo(string $customerId): ?array
    {
        $customer = $this->db->fetchOne(
            'SELECT Id, Name, Token, TokenJwt, TokenJwtCreatedAt, TokenJwtExpiresAt, IsActive, LastSeen 
             FROM Customers WHERE Id = ?',
            [$customerId]
        );
        
        if (!$customer) {
            return null;
        }
        
        $hasJwt = !empty($customer['TokenJwt']);
        $isExpired = false;
        $expiresIn = null;
        
        if ($hasJwt && $customer['TokenJwtExpiresAt']) {
            $expiresAt = strtotime($customer['TokenJwtExpiresAt']);
            $isExpired = $expiresAt < time();
            $expiresIn = max(0, $expiresAt - time());
        }
        
        return [
            'customerId' => $customer['Id'],
            'name' => $customer['Name'],
            'machineToken' => $customer['Token'],
            'hasJwt' => $hasJwt,
            'jwtCreatedAt' => $customer['TokenJwtCreatedAt'],
            'jwtExpiresAt' => $customer['TokenJwtExpiresAt'],
            'isExpired' => $isExpired,
            'expiresIn' => $expiresIn,
            'isActive' => (bool) $customer['IsActive'],
            'lastSeen' => $customer['LastSeen'],
        ];
    }

    /**
     * Lista todos los clientes con tokens JWT activos
     * 
     * @param bool $includeExpired Incluir tokens expirados
     * @return array Lista de clientes con información de tokens
     */
    public function listActiveTokens(bool $includeExpired = false): array
    {
        $query = 'SELECT c.Id, c.Name, c.Email, c.Token, c.TokenJwt, c.TokenJwtCreatedAt, c.TokenJwtExpiresAt, 
                         c.IsActive, c.LastSeen, c.DeviceName
                  FROM Customers c
                  WHERE c.TokenJwt IS NOT NULL';
        
        if (!$includeExpired) {
            $query .= ' AND c.TokenJwtExpiresAt > NOW()';
        }
        
        $query .= ' ORDER BY c.TokenJwtExpiresAt DESC';
        
        $rows = $this->db->fetchAll($query);
        
        return array_map(function($row) {
            $expiresAt = $row['TokenJwtExpiresAt'] ? strtotime($row['TokenJwtExpiresAt']) : null;
            $isExpired = $expiresAt ? $expiresAt < time() : false;
            
            return [
                'customerId' => $row['Id'],
                'name' => $row['Name'],
                'email' => $row['Email'],
                'deviceName' => $row['DeviceName'],
                'machineToken' => $row['Token'],
                'jwtCreatedAt' => $row['TokenJwtCreatedAt'],
                'jwtExpiresAt' => $row['TokenJwtExpiresAt'],
                'isExpired' => $isExpired,
                'expiresIn' => $expiresAt ? max(0, $expiresAt - time()) : null,
                'isActive' => (bool) $row['IsActive'],
                'lastSeen' => $row['LastSeen'],
            ];
        }, $rows);
    }

    /**
     * Obtiene el servicio JWT base
     */
    public function getJwtService(): JwtService
    {
        return $this->jwtService;
    }
}
