<?php

require_once __DIR__ . '/../Models/CustomerRegistryModel.php';

use \Models\CustomerRegistryModel;
class ApiHelper
{
    private static array $sessionConfig;
    public function __construct()
    {
        $appConfig = require __DIR__ . '/../../config/app.php';
        self::$sessionConfig = $appConfig['customerSessions'] ?? [
            'heartbeat_interval' => 60,
            'grace_period' => 180,
            'max_metadata_size' => 2048,
        ];
    }
    public static function respond($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Self::respond([
                'error' => 'Carga JSON inválida',
                'message' => json_last_error_msg()
            ], 400);
        }

        return $data ?? [];
    }

    public static function respondIfOptions(){
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::respond(['status' => 'ok']);
        }
    }

    public static function allowedMethodsPost()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::respond(['error' => 'Método no permitido'], 405);
        }
    }

    public static function allowedMethodsGet()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            self::respond(['error' => 'Método no permitido'], 405);
        }
    }

    public static function allowedMethodsPut()
    {
        $method = self::getEffectiveMethod();
        if ($method !== 'PUT') {
            self::respond(['error' => 'Método no permitido'], 405);
        }
    }

    public static function allowedMethodsDelete()
    {
        $method = self::getEffectiveMethod();
        if ($method !== 'DELETE') {
            self::respond(['error' => 'Método no permitido'], 405);
        }
    }

    public static function allowedMethodsPatch()
    {
        $method = self::getEffectiveMethod();
        if ($method !== 'PATCH') {
            self::respond(['error' => 'Método no permitido'], 405);
        }
    }

    /**
     * Obtiene el método HTTP efectivo, soportando method spoofing
     */
    private static function getEffectiveMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'POST') {
            // Verificar header X-HTTP-Method-Override
            if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
            
            // Verificar parámetro _method en POST
            if (isset($_POST['_method'])) {
                return strtoupper($_POST['_method']);
            }
            
            // Verificar en JSON body
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                static $cachedInput = null;
                if ($cachedInput === null) {
                    $cachedInput = json_decode(file_get_contents('php://input'), true) ?? [];
                }
                if (isset($cachedInput['_method'])) {
                    return strtoupper($cachedInput['_method']);
                }
            }
        }
        
        return strtoupper($method);
    }

    public static function validateMetadata($metadata): void
    {
        if ($metadata === null) {
            return;
        }

        if (!is_array($metadata)) {
            ApiHelper::respond([
                'error' => 'Formato de metadatos inválido',
                'message' => 'Metadata debe ser un objeto'
            ], 422);
        }

        $encoded = json_encode($metadata);
        $maxSize = self::$sessionConfig['max_metadata_size'] ?? 2048;

        if (strlen($encoded) > $maxSize) {
            ApiHelper::respond([
                'error' => 'Metadatos demasiado grandes',
                'message' => "El contenido de metadata excede {$maxSize} bytes"
            ], 413);
        }
    }

    public static function getCustomerIdFromSession($default = null): ?string
    {
        return $GLOBALS['customer_jwt_customer_id'] ?? $default;
    }

    public static function getBillingIdByCustomerIdFromSession($default = null): ?string
    {
        $customerId = $GLOBALS['customer_jwt_customer_id'] ?? $default;

        if ($customerId === null) {
            return null;
        }

        $customerModel = new CustomerRegistryModel();
        $customer = $customerModel->getCustomer($customerId) ?? null;
        
        return $customer['billingId'] ?? null;

    }
}