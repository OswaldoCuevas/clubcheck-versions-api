<?php
// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Inicializar sesión
session_start();

// Incluir sistema de configuración
require_once __DIR__ . '/../config/bootstrap.php';

// Aplicar middleware de autenticación para rutas protegidas
require_once __DIR__ . '/../app/Middleware/AuthMiddleware.php';

try {
    // Debug
    error_log("Index.php - Starting router");
    
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
        '/uploads'  // Permitir acceso a archivos de uploads
    ];
    
    // Aplicar middleware de autenticación solo si no es ruta pública
    if (!in_array($requestUri, $publicRoutes)) {
        Middleware\AuthMiddleware::apply();
    }
    
    // Resolver la ruta
    $router->resolve();
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en el router: " . $e->getMessage());
    
    http_response_code(500);
    echo "Error interno del servidor";
    
    if (ini_get('display_errors')) {
        echo "<br><br>Detalles del error: " . htmlspecialchars($e->getMessage());
        echo "<br>Archivo: " . htmlspecialchars($e->getFile());
        echo "<br>Línea: " . $e->getLine();
    }
}
