<?php

namespace App\Services;

/**
 * Servicio para manejo de JSON Web Tokens (JWT)
 * 
 * Implementación simple usando HMAC SHA256.
 * 
 * Uso:
 *   $jwt = new JwtService();
 *   
 *   // Crear token
 *   $token = $jwt->createToken(['user_id' => 123, 'role' => 'admin'], 3600); // 1 hora
 *   
 *   // Validar token
 *   $payload = $jwt->validateToken($token);
 *   if ($payload) {
 *       echo "Usuario: " . $payload['user_id'];
 *   }
 */
class JwtService
{
    private string $secretKey;
    private string $algorithm = 'HS256';
    private int $defaultExpiration = 3600; // 1 hora por defecto

    /**
     * @param string|null $secretKey Clave secreta para firmar tokens. Si es null, usa la del .env
     */
    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?? $_ENV['JWT_SECRET_KEY'] ?? 'default-secret-key-change-in-production';
    }

    /**
     * Crea un nuevo token JWT
     * 
     * @param array $payload Datos a incluir en el token
     * @param int|null $expiresIn Tiempo de expiración en segundos (null = usa default)
     * @param array $customHeaders Headers adicionales para el token
     * @return string Token JWT
     */
    public function createToken(array $payload, ?int $expiresIn = null, array $customHeaders = []): string
    {
        $issuedAt = time();
        $expiration = $issuedAt + ($expiresIn ?? $this->defaultExpiration);

        // Header
        $header = array_merge([
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ], $customHeaders);

        // Payload con claims estándar
        $payload = array_merge([
            'iat' => $issuedAt,      // Issued at
            'exp' => $expiration,     // Expiration
            'nbf' => $issuedAt,       // Not before
        ], $payload);

        // Codificar header y payload
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        // Crear firma
        $signature = $this->sign("$headerEncoded.$payloadEncoded");
        $signatureEncoded = $this->base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Valida un token JWT y devuelve el payload si es válido
     * 
     * @param string $token Token JWT a validar
     * @return array|null Payload del token o null si es inválido/expirado
     */
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verificar firma
        $expectedSignature = $this->base64UrlEncode(
            $this->sign("$headerEncoded.$payloadEncoded")
        );

        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            return null;
        }

        // Decodificar payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return null;
        }

        // Verificar expiración
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        // Verificar not before
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Verifica si un token es válido (sin devolver el payload)
     * 
     * @param string $token Token JWT
     * @return bool True si el token es válido
     */
    public function isValid(string $token): bool
    {
        return $this->validateToken($token) !== null;
    }

    /**
     * Decodifica un token sin verificar la firma (útil para debugging)
     * 
     * @param string $token Token JWT
     * @return array|null Payload decodificado o null si el formato es inválido
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        return $payload ?: null;
    }

    /**
     * Obtiene información sobre un token (sin validar firma)
     * 
     * @param string $token Token JWT
     * @return array Información del token
     */
    public function getTokenInfo(string $token): array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return ['valid_format' => false];
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        $isExpired = isset($payload['exp']) && $payload['exp'] < time();
        $isNotYetValid = isset($payload['nbf']) && $payload['nbf'] > time();

        return [
            'valid_format' => true,
            'header' => $header,
            'payload' => $payload,
            'is_expired' => $isExpired,
            'is_not_yet_valid' => $isNotYetValid,
            'expires_at' => isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : null,
            'issued_at' => isset($payload['iat']) ? date('Y-m-d H:i:s', $payload['iat']) : null,
            'time_remaining' => isset($payload['exp']) ? max(0, $payload['exp'] - time()) : null,
        ];
    }

    /**
     * Renueva un token (crea uno nuevo con el mismo payload pero nueva expiración)
     * 
     * @param string $token Token JWT actual
     * @param int|null $expiresIn Nueva duración en segundos
     * @return string|null Nuevo token o null si el token original es inválido
     */
    public function refreshToken(string $token, ?int $expiresIn = null): ?string
    {
        $payload = $this->validateToken($token);
        
        if (!$payload) {
            return null;
        }

        // Remover claims de tiempo para que se regeneren
        unset($payload['iat'], $payload['exp'], $payload['nbf']);

        return $this->createToken($payload, $expiresIn);
    }

    /**
     * Establece el tiempo de expiración por defecto
     * 
     * @param int $seconds Segundos
     */
    public function setDefaultExpiration(int $seconds): void
    {
        $this->defaultExpiration = $seconds;
    }

    /**
     * Firma los datos con HMAC SHA256
     */
    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secretKey, true);
    }

    /**
     * Codifica en Base64 URL-safe
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica Base64 URL-safe
     */
    private function base64UrlDecode(string $data): string
    {
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
