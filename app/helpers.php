<?php

// Funciones helper globales para usar en las vistas

/**
 * Genera una URL completa para la aplicación (considerando subdirectorios)
 */
function app_url($path = '/')
{
    require_once __DIR__ . '/Core/UrlHelper.php';
    return \Core\UrlHelper::url($path);
}

/**
 * Verifica si la URL actual coincide con el patrón dado
 */
function is_current_url($pattern)
{
    require_once __DIR__ . '/Core/UrlHelper.php';
    return \Core\UrlHelper::isCurrentUrl($pattern);
}

/**
 * Obtiene la ruta actual sin el subdirectorio base
 */
function current_path()
{
    require_once __DIR__ . '/Core/UrlHelper.php';
    return \Core\UrlHelper::getCurrentPath();
}

/**
 * Genera un token CSRF
 */
function csrf_token()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo hidden para token CSRF
 */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
