<?php

namespace Core;

class Router
{
    private $routes = [];
    private $currentRoute = null;

    public function get($uri, $controller, $method = 'index')
    {
        $this->addRoute('GET', $uri, $controller, $method);
    }

    public function post($uri, $controller, $method = 'store')
    {
        $this->addRoute('POST', $uri, $controller, $method);
    }

    public function any($uri, $controller, $method = 'index')
    {
        $this->addRoute('ANY', $uri, $controller, $method);
    }

    private function addRoute($method, $uri, $controller, $action)
    {
        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'controller' => $controller,
            'action' => $action
        ];
    }

    public function resolve()
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = $this->getRequestUri();

        // Debug
        error_log("Router Debug - Method: $requestMethod, URI: $requestUri");

        foreach ($this->routes as $route) {
            if ($this->matchRoute($route, $requestMethod, $requestUri)) {
                $this->currentRoute = $route;
                return $this->callController($route);
            }
        }

        // Si no se encuentra la ruta, enviar 404
        http_response_code(404);
        echo "404 - Página no encontrada";
    }

    private function getRequestUri()
    {
        $uri = $_SERVER['REQUEST_URI'];
        
        // Remover query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Detectar y remover el subdirectorio de la aplicación
        $scriptName = $_SERVER['SCRIPT_NAME']; // Ej: /clubcheck/public/index.php
        $basePath = str_replace('/public/index.php', '', $scriptName); // Ej: /clubcheck
        
        if ($basePath !== '' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        // Normalizar la URI
        $uri = rtrim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }

        return $uri;
    }

    private function matchRoute($route, $requestMethod, $requestUri)
    {
        // Verificar método HTTP
        if ($route['method'] !== 'ANY' && $route['method'] !== $requestMethod) {
            return false;
        }

        // Verificar URI exacta o con parámetros
        $routeUri = rtrim($route['uri'], '/');
        if (empty($routeUri)) {
            $routeUri = '/';
        }

        return $requestUri === $routeUri;
    }

    private function callController($route)
    {
        $controllerClass = "Controllers\\{$route['controller']}";
        $method = $route['action'];

        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller {$controllerClass} not found");
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            throw new \Exception("Method {$method} not found in {$controllerClass}");
        }

        return $controller->$method();
    }

    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }
}
