<?php

namespace Core;

use App\Exceptions\ApiException;

class Router
{
    private $routes = [];
    private $currentRoute = null;
    private $routeParams = [];
    
    /**
     * Registro de middlewares disponibles
     * Mapea alias a clases de middleware
     */
    private array $middlewareRegistry = [
        'jwt' => \App\Middleware\JwtMiddleware::class,
        'auth' => \Middleware\AuthMiddleware::class,
        'customer_jwt' => \App\Middleware\CustomerJwtMiddleware::class,
    ];

    /**
     * Registra un nuevo middleware en el registry
     */
    public function registerMiddleware(string $alias, string $className): void
    {
        $this->middlewareRegistry[$alias] = $className;
    }

    public function get($uri, $controller, $method = 'index', array $middleware = [])
    {
        $this->addRoute('GET', $uri, $controller, $method, $middleware);
    }

    public function post($uri, $controller, $method = 'store', array $middleware = [])
    {
        $this->addRoute('POST', $uri, $controller, $method, $middleware);
    }

    public function put($uri, $controller, $method = 'update', array $middleware = [])
    {
        $this->addRoute('PUT', $uri, $controller, $method, $middleware);
    }

    public function delete($uri, $controller, $method = 'destroy', array $middleware = [])
    {
        $this->addRoute('DELETE', $uri, $controller, $method, $middleware);
    }

    public function patch($uri, $controller, $method = 'update', array $middleware = [])
    {
        $this->addRoute('PATCH', $uri, $controller, $method, $middleware);
    }

    public function any($uri, $controller, $method = 'index', array $middleware = [])
    {
        $this->addRoute('ANY', $uri, $controller, $method, $middleware);
    }

    private function addRoute($method, $uri, $controller, $action, array $middleware = [])
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
            'middleware' => $middleware,
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
        $this->handleNotFound($requestUri);
    }

    /**
     * Maneja errores 404 (ruta no encontrada)
     */
    private function handleNotFound(string $requestUri): void
    {
        // Si es una petición API, responder con JSON
        if ($this->isApiRequest($requestUri)) {
            $this->respondJson([
                'success' => false,
                'error' => 'Endpoint no encontrado',
                'error_code' => 'ROUTE_NOT_FOUND'
            ], 404);
            return;
        }

        // Para peticiones web normales
        http_response_code(404);
        echo "404 - Página no encontrada";
    }

    /**
     * Determina si la petición es una API request
     */
    private function isApiRequest(string $uri): bool
    {
        return strpos($uri, '/api/') === 0;
    }

    /**
     * Responde con JSON
     */
    private function respondJson(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
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

        try {
            // Ejecutar middlewares antes del controlador
            $this->runMiddleware($route['middleware'] ?? []);
            
            return call_user_func_array([$controller, $method], $this->routeParams);
        } catch (ApiException $e) {
            // Excepción controlada - responder con JSON formateado
            $e->respond();
        } catch (\Exception $e) {
            // Excepción no controlada - loguear y responder error genérico
            error_log("Unhandled Exception in {$controllerClass}@{$method}: " . $e->getMessage());
            error_log($e->getTraceAsString());

            // En producción no mostrar detalles del error
            $isProduction = ($_ENV['APP_MODE'] ?? 'DEV') === 'PROD';
            
            $this->respondJson([
                'success' => false,
                'error' => $isProduction ? 'Error interno del servidor' : $e->getMessage(),
                'error_code' => 'INTERNAL_ERROR',
                'debug' => $isProduction ? null : [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }

    /**
     * Ejecuta los middlewares de la ruta
     * 
     * @param array $middlewares Array de nombres de middleware
     * @throws \Exception Si un middleware falla
     */
    private function runMiddleware(array $middlewares): void
    {
        foreach ($middlewares as $middlewareName) {
            // Verificar si el middleware tiene parámetros (ej: 'role:admin')
            $params = [];
            if (strpos($middlewareName, ':') !== false) {
                [$middlewareName, $paramString] = explode(':', $middlewareName, 2);
                $params = explode(',', $paramString);
            }

            // Buscar la clase del middleware
            $middlewareClass = $this->middlewareRegistry[$middlewareName] ?? null;

            if (!$middlewareClass) {
                throw new \Exception("Middleware '{$middlewareName}' no registrado");
            }

            if (!class_exists($middlewareClass)) {
                throw new \Exception("Clase de middleware '{$middlewareClass}' no encontrada");
            }

            // Instanciar y ejecutar el middleware
            $middleware = new $middlewareClass();

            if (method_exists($middleware, 'handle')) {
                $middleware->handle(...$params);
            } else {
                throw new \Exception("Middleware '{$middlewareName}' no tiene método handle()");
            }
        }
    }

    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }
}
