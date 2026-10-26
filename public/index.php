<?php
// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
// Evita que avisos de PHP contaminen respuestas JSON antes de cargar el entorno.
// config/bootstrap.php vuelve a habilitarlos cuando la aplicación está en desarrollo.
ini_set('display_errors', '0');

// Inicializar sesión
session_start();

// Cargar variables de entorno desde .env si existe
$envLoaderPath = __DIR__ . '/../config/env_loader.php';
if (file_exists($envLoaderPath)) {
    require_once $envLoaderPath;
    EnvLoader::load(__DIR__ . '/../.env');
}

// Incluir sistema de configuración
require_once __DIR__ . '/../config/bootstrap.php';

/** Configura CORS del flujo members por entorno y conserva el contrato de los clientes existentes. */
function sendCorsHeaders(): void
{
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (preg_match('#/api/(members|member-management)(/|$)#', $requestPath)) {
        $origins = array_filter(array_map('trim', explode(',', (string) getenv('MEMBERS_CORS_ORIGINS'))));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, $origins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, RequestId, Request-Id, X-Request-Id');
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        return;
    }
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Access-Token, X-Customer-JWT, X-Desktop-JWT, X-Device-Name, X-Client-Version, X-HTTP-Method-Override');
    header('Access-Control-Max-Age: 86400');
}

sendCorsHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    // Cargar el sistema de rutas
    require_once __DIR__ . '/../app/Core/Router.php';
    
    $router = require_once __DIR__ . '/../routes/web.php';
    
    // Verificar si es una ruta pública antes de aplicar middleware
    $requestUri = $_SERVER['REQUEST_URI'];
    
    // Remover query string para la verificación
    if (($pos = strpos($requestUri, '?')) !== false) {
        $requestUri = substr($requestUri, 0, $pos);
    }

    // Detectar y remover el subdirectorio de la aplicación
    $scriptName = $_SERVER['SCRIPT_NAME']; // Ej: /clubcheck/public/index.php
    $basePath = str_replace('/public/index.php', '', $scriptName); // Ej: /clubcheck
    
    if ($basePath !== '' && strpos($requestUri, $basePath) === 0) {
        $requestUri = substr($requestUri, strlen($basePath));
    }
    
    // Normalizar la URI
    $requestUri = rtrim($requestUri, '/');
    if (empty($requestUri)) {
        $requestUri = '/';
    }

    // Rutas públicas que no requieren autenticación
    $publicRoutes = [
        '/login',
        '/api/version',
        '/api/check-update', 
        '/api/download',
        '/uploads',  // Permitir acceso a archivos de uploads
        '/api/customers/sessions/start',
        '/api/customers/sessions/heartbeat',
        '/api/customers/sessions/end',
        '/api/customers/sessions/active',
        '/api/customers',
        '/api/customers/save',
        '/api/customers/token',
        '/api/customers/token/register',
        '/api/customers/token/await',
        '/api/customers/validate',
        '/api/licenses/validate',
        '/favicon.ico',
        '/favicon.png',
        '/apple-touch-icon.png',
        '/apple-touch-icon-precomposed.png',
        '/api/server/timestamp' // Nueva ruta pública para obtener el timestamp del servidor
    ];

    $isPublicRoute = in_array($requestUri, $publicRoutes);

    if (!$isPublicRoute) {
        $apiPrefixes = [
            '/api/members/',
            '/api/member-management/',
            '/public/assets',
            '/assets',
            '/api/customers/sessions',
            '/api/customers',
            '/api/desktop',
            '/api/email'  // Rutas de email públicas (recuperación de contraseña, etc.)
        ];

        foreach ($apiPrefixes as $prefix) {
            if (strpos($requestUri, $prefix) === 0) {
                $isPublicRoute = true;
                break;
            }
        }
    }

    // Permitir solicitudes OPTIONS para CORS sin autenticación
    if (!$isPublicRoute && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        $isPublicRoute = true;
    }

    // Aplicar middleware de autenticación solo si no es ruta pública
    if (!$isPublicRoute) {
        require_once __DIR__ . '/../app/Middleware/AuthMiddleware.php';
        Middleware\AuthMiddleware::apply();
    }

    // Resolver la ruta
    $router->resolve();
    
} catch (\Throwable $e) {
    // Manejo de errores
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
