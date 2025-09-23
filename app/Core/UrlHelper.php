<?php

namespace Core;

class UrlHelper
{
    private static $basePath = null;

    /**
     * Obtiene la ruta base de la aplicación
     */
    public static function getBasePath()
    {
        if (self::$basePath === null) {
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $basePath = str_replace('/public/index.php', '', $scriptName);
            self::$basePath = $basePath;
        }
        
        return self::$basePath;
    }

    /**
     * Genera una URL completa para la aplicación
     */
    public static function url($path = '/')
    {
        $basePath = self::getBasePath();
        $url = $basePath . $path;
        
        // Normalizar la URL
        $url = rtrim($url, '/');
        if (empty($url)) {
            $url = $basePath ?: '/';
        }
        
        return $url;
    }

    /**
     * Genera una URL absoluta completa con protocolo y dominio
     */
    public static function absoluteUrl($path = '/')
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = self::getBasePath();
        
        $url = $basePath . $path;
        
        // Normalizar la URL
        $url = rtrim($url, '/');
        if (empty($url)) {
            $url = $basePath ?: '/';
        }
        
        return $protocol . '://' . $host . $url;
    }

    /**
     * Genera una URL de redirección
     */
    public static function redirect($path = '/')
    {
        $url = self::url($path);
        header("Location: $url");
        exit;
    }

    /**
     * Verifica si la URL actual coincide con el patrón dado
     */
    public static function isCurrentUrl($pattern)
    {
        $currentPath = self::getCurrentPath();
        return $currentPath === $pattern;
    }

    /**
     * Obtiene la ruta actual sin el subdirectorio base
     */
    public static function getCurrentPath()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remover query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remover el subdirectorio base
        $basePath = self::getBasePath();
        if ($basePath !== '' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        // Normalizar
        $uri = rtrim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }

        return $uri;
    }
}
