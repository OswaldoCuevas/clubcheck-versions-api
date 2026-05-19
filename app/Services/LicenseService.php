<?php

namespace App\Services;

/**
 * Servicio de firma y verificación de licencias
 *
 * Usa RSA-2048 + SHA-256 para firmar licencias digitalmente.
 *
 * FLUJO:
 *   Servidor  → firma con CLAVE PRIVADA   (solo el servidor puede crear licencias)
 *   Cliente   → verifica con CLAVE PÚBLICA (solo puede leer/verificar, nunca firmar)
 *
 * CONFIGURACIÓN REQUERIDA EN .env:
 *   LICENSE_PRIVATE_KEY=<PEM completo con \n escapados, o en base64>
 *   LICENSE_PUBLIC_KEY=<PEM completo con \n escapados, o en base64>
 *
 * Para generar el par de claves ejecuta:
 *   php utils/generate_license_keys.php
 */
class LicenseService
{
    private string $privateKeyPem;
    private string $publicKeyPem;

    public function __construct()
    {
        $this->privateKeyPem = $this->loadKey('LICENSE_PRIVATE_KEY');
        $this->publicKeyPem  = $this->loadKey('LICENSE_PUBLIC_KEY');
    }

    // ==================== GENERACIÓN ====================

    /**
     * Genera una licencia firmada para un cliente.
     *
     * Devuelve un token con formato:
     *   base64url(header) . "." . base64url(payload) . "." . base64url(signature)
     *
     * @param string   $customerId    ID del cliente (Stripe customer ID)
     * @param string   $customerName  Nombre completo del cliente
     * @param string   $customerEmail Email del cliente
     * @param string   $planLookupKey Clave del plan (ej: "professional_monthly")
     * @param string   $planName      Nombre legible del plan
     * @param bool     $isPermanent   true = licencia permanente (sin expiración)
     * @param int|null $expiresAt     Timestamp Unix de expiración (null si es permanente)
     * @return string  Token de licencia firmado
     */
    public function generateLicense(
        string $customerId,
        string $customerName,
        string $customerEmail,
        string $planLookupKey,
        string $planName,
        bool $isPermanent = false,
        ?int $expiresAt = null,
        ?string $machineToken = null,
        ?array $rules = null,
        ?string $customerJwt = null
    ): string {
        $header = $this->b64uEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'LIC',
        ]));

        $payload = $this->b64uEncode(json_encode([
            'sub'       => $customerId,
            'name'      => $customerName,
            'email'     => $customerEmail,
            'plan'      => $planLookupKey,
            'plan_name' => $planName,
            'permanent' => $isPermanent,
            'machine'   => $machineToken,
            'rules'     => $rules,
            'customer_jwt' => $customerJwt,
            'iat'       => time(),
            'exp'       => $expiresAt,
        ], JSON_UNESCAPED_UNICODE));

        $signingInput = $header . '.' . $payload;

        $privateKey = openssl_pkey_get_private($this->privateKeyPem);
        if (!$privateKey) {
            throw new \RuntimeException('No se pudo cargar la clave privada de licencia. Verifica LICENSE_PRIVATE_KEY.');
        }

        $rawSignature = '';
        if (!openssl_sign($signingInput, $rawSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Error al firmar la licencia con la clave privada.');
        }

        return $signingInput . '.' . $this->b64uEncode($rawSignature);
    }

    /**
     * Genera el contenido del archivo .lic para entregar al cliente.
     *
     * El archivo incluye:
     *  - Un encabezado informativo (comentado con #)
     *  - El token de licencia firmado
     *
     * Cualquier modificación al token invalidará la firma.
     */
    public function generateLicenseFile(
        string $licenseToken,
        string $customerName,
        string $planName,
        bool $isPermanent = false,
        ?int $expiresAt = null,
        ?string $machineToken = null,
        ?array $rules = null
    ): string {
        return implode("\n", [
            '-----BEGIN CLUBCHECK LICENSE-----',
            $licenseToken,
            '-----END CLUBCHECK LICENSE-----',
        ]);
    }

    // ==================== VERIFICACIÓN ====================

    /**
     * Verifica una licencia firmada y devuelve sus datos si es válida.
     *
     * Este método puede usarse tanto en el servidor como en el cliente
     * (solo requiere la clave pública).
     *
     * @param string $licenseToken Token en formato header.payload.signature
     * @return array ['valid' => bool, 'payload' => array|null, 'error' => string|null]
     */
    public function verifyLicense(string $licenseToken): array
    {
        $parts = explode('.', trim($licenseToken));
        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'Formato de licencia inválido'];
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $signingInput = $headerB64 . '.' . $payloadB64;

        $rawSignature = $this->b64uDecode($sigB64);

        $publicKey = openssl_pkey_get_public($this->publicKeyPem);
        if (!$publicKey) {
            return ['valid' => false, 'error' => 'No se pudo cargar la clave pública de licencia'];
        }

        $result = openssl_verify($signingInput, $rawSignature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result === -1) {
            return ['valid' => false, 'error' => 'Error interno al verificar la firma: ' . openssl_error_string()];
        }

        if ($result !== 1) {
            return ['valid' => false, 'error' => 'Firma de licencia inválida — el archivo pudo haber sido modificado'];
        }

        $payload = json_decode($this->b64uDecode($payloadB64), true);
        if (!$payload) {
            return ['valid' => false, 'error' => 'Datos de licencia corruptos'];
        }

        // Verificar expiración (solo si no es permanente y tiene exp)
        if (!empty($payload['exp']) && $payload['exp'] < time()) {
            return [
                'valid'   => false,
                'error'   => 'Licencia expirada',
                'payload' => $payload,
            ];
        }

        return [
            'valid'   => true,
            'payload' => $payload,
        ];
    }

    /**
     * Extrae y verifica la licencia desde el contenido de un archivo .lic
     *
     * @param string $fileContent Contenido completo del archivo .lic
     * @return array  Resultado de verifyLicense, o ['valid' => false, 'error' => ...]
     */
    public function verifyLicenseFile(string $fileContent): array
    {
        $token = $this->parseLicenseFile($fileContent);
        if ($token === null) {
            return ['valid' => false, 'error' => 'El archivo no es un archivo de licencia ClubCheck válido'];
        }

        return $this->verifyLicense($token);
    }

    /**
     * Extrae solo el token desde el contenido de un archivo .lic
     */
    public function parseLicenseFile(string $fileContent): ?string
    {
        if (!preg_match(
            '/-----BEGIN CLUBCHECK LICENSE-----\s.*?^\s*([A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+)\s*\n-----END CLUBCHECK LICENSE-----/ms',
            $fileContent,
            $matches
        )) {
            return null;
        }

        return trim($matches[1]);
    }

    // ==================== EMAIL ====================

    /**
     * Genera la licencia y la envía por correo al cliente como archivo adjunto.
     *
     * @param EmailService $emailService  Instancia del servicio de email
     * @param string $customerEmail       Email destino
     * @param string $customerId          ID Stripe del cliente
     * @param string $customerName        Nombre del cliente
     * @param string $planLookupKey       Clave del plan
     * @param string $planName            Nombre legible del plan
     * @param bool   $isPermanent         true = sin expiración
     * @param int|null $expiresAt         Timestamp de expiración
     * @return array ['success' => bool, ...]
     */
    public function sendLicenseByEmail(
        EmailService $emailService,
        string $customerEmail,
        string $customerId,
        string $customerName,
        string $planLookupKey,
        string $planName,
        bool $isPermanent = false,
        ?int $expiresAt = null,
        ?string $machineToken = null,
        ?string $customerJwt = null
    ): array {
        try {
            $token       = $this->generateLicense($customerId, $customerName, $customerEmail, $planLookupKey, $planName, $isPermanent, $expiresAt, $machineToken, null, $customerJwt);
            $fileContent = $this->generateLicenseFile($token, $customerName, $planName, $isPermanent, $expiresAt, $machineToken);
            $fileName    = 'ClubCheck_License_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $customerName) . '.lic';

            $expiresText = $isPermanent
                ? '<strong>Licencia Permanente</strong> (sin vencimiento)'
                : ($expiresAt ? date('d/m/Y', $expiresAt) : 'N/A');

            $body = "
                <p>Hola <strong>{$customerName}</strong>,</p>
                <p>Gracias por tu compra. Adjunto encontrarás tu licencia de <strong>ClubCheck</strong>.</p>
                <table style='border-collapse:collapse;font-family:monospace;'>
                    <tr><td style='padding:4px 12px 4px 0'><strong>Plan:</strong></td><td>{$planName}</td></tr>
                    <tr><td style='padding:4px 12px 4px 0'><strong>Vigencia:</strong></td><td>{$expiresText}</td></tr>
                    <tr><td style='padding:4px 12px 4px 0'><strong>Email:</strong></td><td>{$customerEmail}</td></tr>
                </table>
                <p style='color:#666;font-size:13px;margin-top:24px;'>
                    Este archivo está firmado digitalmente. No lo modifiques, pues dejará de ser válido.
                </p>
            ";

            return $emailService->send(
                $customerEmail,
                'Tu licencia de ClubCheck — ' . $planName,
                $body,
                'NORMAL',
                [
                    'toName'      => $customerName,
                    'attachments' => [
                        [
                            'content' => $fileContent,
                            'name'    => $fileName,
                            'mime'    => 'application/octet-stream',
                        ],
                    ],
                ]
            );
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => 'Error al generar o enviar la licencia: ' . $e->getMessage(),
            ];
        }
    }

    // ==================== UTILIDADES PÚBLICAS ====================

    /**
     * Devuelve la clave pública PEM para distribuir con el cliente.
     * NUNCA distribuyas la clave privada.
     */
    public function getPublicKeyPem(): string
    {
        return $this->publicKeyPem;
    }

    // ==================== HELPERS PRIVADOS ====================

    /**
     * Carga una clave PEM desde variable de entorno.
     * Acepta:
     *  - PEM directo (con -----BEGIN...----- )
     *  - PEM con \n escapados (como se almacena en .env)
     *  - PEM codificado en base64
     */
    private function loadKey(string $envVar): string
    {
        $value = $_ENV[$envVar] ?? getenv($envVar) ?? '';
        if (empty($value)) {
            throw new \RuntimeException(
                "Variable de entorno {$envVar} no está configurada. " .
                "Ejecuta: php utils/generate_license_keys.php"
            );
        }

        // Reemplazar \n literales por saltos de línea reales (formato .env)
        $value = str_replace('\\n', "\n", $value);

        // Si ya es un PEM válido, devolverlo directamente
        if (str_contains($value, '-----BEGIN')) {
            return $value;
        }

        // Intentar decodificar como base64
        $decoded = base64_decode($value, true);
        if ($decoded !== false && str_contains($decoded, '-----BEGIN')) {
            return $decoded;
        }

        throw new \RuntimeException("Formato inválido para {$envVar}. Debe ser PEM o PEM en base64.");
    }

    /** Codifica en base64url (sin padding) */
    private function b64uEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Decodifica base64url */
    private function b64uDecode(string $data): string
    {
        $padLen  = (4 - strlen($data) % 4) % 4;
        $padded  = $data . str_repeat('=', $padLen);
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
