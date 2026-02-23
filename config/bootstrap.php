<?php

// Autoloader para clases del proyecto
spl_autoload_register(function ($className) {
    // Convertir namespace a ruta de archivo
    $classPath = str_replace('\\', '/', $className);
    
    // Buscar en el directorio app
    $appPath = __DIR__ . '/../app/' . $classPath . '.php';
    if (file_exists($appPath)) {
        require_once $appPath;
        return;
    }
    
    // Buscar en subdirectorios de app
    $paths = [
        __DIR__ . '/../app/Controllers/',
        __DIR__ . '/../app/Models/',
        __DIR__ . '/../app/Core/',
    ];
    
    foreach ($paths as $path) {
        $file = $path . basename($classPath) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Función helper para cargar configuración
function config($key = null, $default = null) {
    static $config = null;
    
    if ($config === null) {
        $config = require __DIR__ . '/app.php';
    }
    
    if ($key === null) {
        return $config;
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

// Función helper para obtener rutas
function path($key, $subPath = '') {
    $basePath = config("paths.{$key}");
    return $basePath ? rtrim($basePath, '/') . '/' . ltrim($subPath, '/') : null;
}

// Función helper para URLs
function url($path = '') {
    $baseUrl = config('app.url');
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

// Función helper para debug
function dd($data) {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die();
}

// Función helper para logging
function logger($message, $level = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}";
    
    // Usar error_log en lugar de file_put_contents (más compatible con servidores restrictivos)
    error_log($logMessage);
    
    // Intentar escribir en archivo solo si es posible
    $logFile = path('logs', 'app.log');
    if ($logFile && is_writable(dirname($logFile))) {
        @file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

// Configurar reporte de errores según el entorno
if (config('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configurar zona horaria
date_default_timezone_set(config('app.timezone', 'UTC'));

// Incluir funciones helper globales
require_once __DIR__ . '/../app/helpers.php';
