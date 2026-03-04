<?php

namespace Core;

class Router
{
    private $routes = [];
    private $currentRoute = null;
    private $routeParams = [];

    public function get($uri, $controller, $method = 'index')
    {
        $this->addRoute('GET', $uri, $controller, $method);
    }

    public function post($uri, $controller, $method = 'store')
    {
        $this->addRoute('POST', $uri, $controller, $method);
    }

    public function put($uri, $controller, $method = 'update')
    {
        $this->addRoute('PUT', $uri, $controller, $method);
    }

    public function delete($uri, $controller, $method = 'destroy')
    {
        $this->addRoute('DELETE', $uri, $controller, $method);
    }

    public function patch($uri, $controller, $method = 'update')
    {
        $this->addRoute('PATCH', $uri, $controller, $method);
    }

    public function any($uri, $controller, $method = 'index')
    {
        $this->addRoute('ANY', $uri, $controller, $method);
    }

    private function addRoute($method, $uri, $controller, $action)
    {
        $normalizedUri = rtrim($uri, '/');
        if ($normalizedUri === '') {
            $normalizedUri = '/';
        }

        $paramNames = [];
        $pattern = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $normalizedUri);

        $hasParams = !empty($paramNames);
        $regex = $hasParams ? '#^' . $pattern . '$#' : null;

        $this->routes[] = [
            'method' => $method,
            'uri' => $normalizedUri,
            'controller' => $controller,
            'action' => $action,
            'hasParams' => $hasParams,
            'regex' => $regex,
            'paramNames' => $paramNames,
        ];
    }

    public function resolve()
    {
        // Obtener método HTTP real, soportando method spoofing
        $requestMethod = $this->getRequestMethod();
        $requestUri = $this->getRequestUri();

        // Debug
        error_log("Router Debug - Method: $requestMethod, URI: $requestUri");

        $this->routeParams = [];

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

    private function getRequestMethod()
    {
        // Soportar method spoofing para PUT/DELETE desde formularios o clientes que solo soportan GET/POST
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Verificar si hay un override del método en POST
        if ($method === 'POST') {
            // Verificar header X-HTTP-Method-Override
            if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
            
            // Verificar parámetro _method en el body
            if (isset($_POST['_method'])) {
                return strtoupper($_POST['_method']);
            }
            
            // Verificar en JSON body
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
                if (isset($input['_method'])) {
                    return strtoupper($input['_method']);
                }
            }
        }
        
        return strtoupper($method);
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
        $routeUri = $route['uri'];

        if (!$route['hasParams']) {
            return $requestUri === $routeUri;
        }

        if ($route['regex'] && preg_match($route['regex'], $requestUri, $matches)) {
            $this->routeParams = [];
            foreach ($route['paramNames'] as $name) {
                $this->routeParams[] = $matches[$name] ?? null;
            }
            return true;
        }

        return false;
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

        return call_user_func_array([$controller, $method], $this->routeParams);
    }

    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }
}
