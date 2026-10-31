<?php

namespace Core;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\JsonResponse;

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
        'desktop_jwt' => \App\Middleware\DesktopJwtMiddleware::class,
        'push_client_jwt' => \App\Middleware\PushClientJwtMiddleware::class,
        'permission' => \Middleware\PermissionMiddleware::class,
    ];

    public function __construct(private ?Container $container = null)
    {
    }

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
        $uriMatches = $this->findRoutesByUri($requestUri);

        if ($uriMatches === []) {
            $this->handleNotFound($requestUri);
            return;
        }

        $routeMatch = $this->findRouteByMethod($uriMatches, $requestMethod);

        if ($routeMatch === null) {
            $this->handleMethodNotAllowed($requestUri, $uriMatches);
            return;
        }

        $this->currentRoute = $routeMatch['route'];
        $this->routeParams = $routeMatch['params'];

        return $this->callController($routeMatch['route']);
    }

    /**
     * Busca primero rutas estáticas para evitar que una URI conocida termine
     * tratándose como un parámetro de una ruta dinámica.
     *
     * @return list<array{route: array<string, mixed>, params: list<string|null>}>
     */
    private function findRoutesByUri(string $requestUri): array
    {
        $staticMatches = [];
        $dynamicMatches = [];

        foreach ($this->routes as $route) {
            $params = $this->matchRouteUri($route, $requestUri);

            if ($params === null) {
                continue;
            }

            $match = [
                'route' => $route,
                'params' => $params,
            ];

            if ($route['hasParams']) {
                $dynamicMatches[] = $match;
                continue;
            }

            $staticMatches[] = $match;
        }

        return $staticMatches !== [] ? $staticMatches : $dynamicMatches;
    }

    /**
     * @param list<array{route: array<string, mixed>, params: list<string|null>}> $uriMatches
     * @return array{route: array<string, mixed>, params: list<string|null>}|null
     */
    private function findRouteByMethod(array $uriMatches, string $requestMethod): ?array
    {
        foreach ($uriMatches as $match) {
            $routeMethod = $match['route']['method'];

            if ($routeMethod === 'ANY' || $routeMethod === $requestMethod) {
                return $match;
            }
        }

        return null;
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
                'error_code' => ApiErrorCode::RouteNotFound->value
            ], 404);
            return;
        }

        // Para peticiones web normales
        http_response_code(404);
        echo "404 - Página no encontrada";
    }

    /**
     * @param list<array{route: array<string, mixed>, params: list<string|null>}> $uriMatches
     */
    private function handleMethodNotAllowed(string $requestUri, array $uriMatches): void
    {
        $allowedMethods = [];

        foreach ($uriMatches as $match) {
            $method = (string) $match['route']['method'];

            if ($method !== 'ANY' && !in_array($method, $allowedMethods, true)) {
                $allowedMethods[] = $method;
            }
        }

        if ($allowedMethods !== []) {
            header('Allow: ' . implode(', ', $allowedMethods));
        }

        if ($this->isApiRequest($requestUri)) {
            JsonResponse::send(
                ['error' => 'Método no permitido'],
                ApiErrorCode::MethodNotAllowed->httpStatus(),
            );
        }

        http_response_code(ApiErrorCode::MethodNotAllowed->httpStatus());
        echo '405 - Método no permitido';
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

    /** @return list<string|null>|null */
    private function matchRouteUri(array $route, string $requestUri): ?array
    {
        $routeUri = $route['uri'];

        if (!$route['hasParams']) {
            return $requestUri === $routeUri ? [] : null;
        }

        if ($route['regex'] && preg_match($route['regex'], $requestUri, $matches)) {
            $params = [];

            foreach ($route['paramNames'] as $name) {
                $params[] = $matches[$name] ?? null;
            }

            return $params;
        }

        return null;
    }

    private function callController($route)
    {
        $configuredController = ltrim((string) $route['controller'], '\\');
        $controllerClass = str_contains($configuredController, '\\')
            ? $configuredController
            : "Controllers\\{$configuredController}";
        $method = $route['action'];

        try {
            if (!class_exists($controllerClass)) {
                throw new \Exception("Controller {$controllerClass} not found");
            }

            // Ejecutar middlewares antes del controlador
            $this->runMiddleware($route['middleware'] ?? []);

            $controller = $this->resolveClass($controllerClass);
            if (!method_exists($controller, $method)) {
                throw new \Exception("Method {$method} not found in {$controllerClass}");
            }
            
            return call_user_func_array([$controller, $method], $this->routeParams);
        } catch (ApiException $e) {
            // Excepción controlada - responder con JSON formateado
            JsonResponse::send($e->toArray(), $e->getStatusCode());
        } catch (\Throwable $e) {
            // Excepción no controlada - loguear y responder error genérico
            $errorId = 'err_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
            $logMessage = sprintf(
                '[%s] Unhandled %s in %s@%s: %s in %s:%d%s%s',
                $errorId,
                get_class($e),
                $controllerClass,
                $method,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                PHP_EOL,
                $e->getTraceAsString()
            );
            function_exists('logger') ? logger($logMessage, 'error') : error_log($logMessage);

            // APP_DEBUG controla los detalles incluso cuando APP_MODE=PROD.
            $showDebug = (bool) config('app.debug', false);
            
            $response = [
                'success' => false,
                'error' => $showDebug ? $e->getMessage() : 'Error interno del servidor',
                'error_code' => ApiErrorCode::InternalError->value,
                'error_id' => $errorId,
            ];
            if ($showDebug) {
                $response['debug'] = [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode(PHP_EOL, $e->getTraceAsString()),
                ];
            }
            $this->respondJson($response, 500);
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
            $middleware = $this->resolveClass($middlewareClass);

            if (method_exists($middleware, 'handle')) {
                $middleware->handle(...$params);
            } else {
                throw new \Exception("Middleware '{$middlewareName}' no tiene método handle()");
            }
        }
    }

    private function resolveClass(string $className): object
    {
        if ($this->container !== null) {
            return $this->container->get($className);
        }

        return new $className();
    }

    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }
}
