<?php
// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicializar sesión
session_start();

// Incluir sistema de configuración
require_once __DIR__ . '/../config/bootstrap.php';

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
        '/api/customers/token/await'
    ];

    $isPublicRoute = in_array($requestUri, $publicRoutes);

    if (!$isPublicRoute) {
        $apiPrefixes = [
            '/api/customers/sessions',
            '/api/customers'
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
    
} catch (Exception $e) {
    // Manejo de errores
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
