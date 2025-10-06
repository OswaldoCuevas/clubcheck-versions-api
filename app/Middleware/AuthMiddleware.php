<?php

namespace Middleware;

use Core\UrlHelper;

require_once __DIR__ . '/../Core/UrlHelper.php';

class AuthMiddleware
{
    /**
     * Rutas públicas que no requieren autenticación
     */
    private static $publicRoutes = [
        '/login',
        '/api/version',
        '/api/check-update',
        '/api/download',
        '/api/customers/sessions/start',
        '/api/customers/sessions/heartbeat',
        '/api/customers/sessions/end',
    '/api/customers/sessions/active',
    '/api/customers',
    '/api/customers/save',
    '/api/customers/register',
    '/api/customers/token',
    '/api/customers/token/register',
    '/api/customers/token/await',
        'version.json',
        // Legacy compatibility
        'login.php',
        'api.php',
        'check-update.php',
        'download.php'
    ];

    /**
     * Verificar si la ruta actual requiere autenticación
     */
    public static function requiresAuth()
    {
        $currentScript = basename($_SERVER['SCRIPT_NAME']);
        $requestUri = $_SERVER['REQUEST_URI'];
        
        // Verificar si es una ruta pública
        $publicPrefixes = [
            '/api/customers/sessions',
            '/api/customers'
        ];

        foreach (self::$publicRoutes as $route) {
            if ($currentScript === $route || strpos($requestUri, $route) !== false) {
                return false;
            }
        }

        foreach ($publicPrefixes as $prefix) {
            if (strpos($requestUri, $prefix) === 0) {
                return false;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return false;
        }
        
        // Verificar si es acceso directo a archivos de uploads
        if (strpos($requestUri, '/uploads/') !== false) {
            return false;
        }
        
        return true;
    }

    /**
     * Aplicar middleware de autenticación
     */
    public static function apply()
    {
        // Solo aplicar si la ruta requiere autenticación
        if (!self::requiresAuth()) {
            return;
        }

        // Incluir modelo de usuario si no está cargado
        if (!class_exists('\Models\UserModel')) {
            require_once __DIR__ . '/../Models/UserModel.php';
        }

        $userModel = new \Models\UserModel();
        
        // Si no está autenticado, redirigir al login
        if (!$userModel->isAuthenticated()) {
            // Guardar la URL actual (sin base path) para redirigir después del login
            $_SESSION['redirect_after_login'] = UrlHelper::getCurrentPath();

            // Redirigir al login normalizado
            header('Location: ' . UrlHelper::url('/login'));
            exit;
        }
    }

    /**
     * Verificar permisos específicos
     */
    public static function requirePermission($permission)
    {
        if (!class_exists('\Models\UserModel')) {
            require_once __DIR__ . '/../Models/UserModel.php';
        }

        $userModel = new \Models\UserModel();
        
        if (!$userModel->isAuthenticated()) {
            $_SESSION['redirect_after_login'] = UrlHelper::getCurrentPath();
            header('Location: ' . UrlHelper::url('/login'));
            exit;
        }

        if (!$userModel->hasPermission($permission)) {
            // Redirigir a una página de error o al dashboard
            $_SESSION['error_message'] = 'No tienes permisos para acceder a esta funcionalidad';
            header('Location: ' . UrlHelper::url('/'));
            exit;
        }
    }
}
