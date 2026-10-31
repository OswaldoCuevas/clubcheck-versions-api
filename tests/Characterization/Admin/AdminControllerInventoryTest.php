<?php

declare(strict_types=1);

namespace Tests\Characterization\Admin;

use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Tests\Characterization\Support\AdminHttpTestCase;

#[Group('characterization')]
final class AdminControllerInventoryTest extends AdminHttpTestCase
{
    protected function shouldResetFixtures(): bool
    {
        return false;
    }

    public function test_every_public_admin_controller_method_has_a_versioned_http_contract(): void
    {
        $controllers = [];
        foreach (self::inventory()['endpoints'] as $endpoint) {
            $controller = $endpoint['controller'];
            $controllers[$controller] = $controller;
        }

        foreach ($controllers as $controllerName => $controllerClass) {
            $reflection = new ReflectionClass($controllerClass);
            $actualMethods = array_values(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                array_filter(
                    $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                    static fn (ReflectionMethod $method): bool => $method->getName() !== '__construct'
                        && $method->getDeclaringClass()->getName() === $controllerClass
                )
            ));
            sort($actualMethods);

            $inventoriedMethods = array_values(array_map(
                static fn (array $endpoint): string => $endpoint['controllerMethod'],
                array_filter(
                    self::inventory()['endpoints'],
                    static fn (array $endpoint): bool => $endpoint['controller'] === $controllerName
                )
            ));
            sort($inventoriedMethods);

            self::assertSame($actualMethods, $inventoriedMethods, $controllerName);
        }
    }

    public function test_inventory_paths_and_methods_match_the_real_router(): void
    {
        require_once dirname(__DIR__, 3) . '/app/Core/Router.php';
        $router = require dirname(__DIR__, 3) . '/routes/web.php';
        $reflection = new ReflectionClass($router);
        $routes = $reflection->getProperty('routes')->getValue($router);

        foreach (self::inventory()['endpoints'] as $endpoint) {
            $matches = array_filter($routes, static function (array $route) use ($endpoint): bool {
                $controller = $endpoint['controller'];
                if ($route['controller'] !== $controller || $route['action'] !== $endpoint['controllerMethod']) {
                    return false;
                }
                $method = $endpoint['request']['method'];
                return $route['method'] === 'ANY' || $route['method'] === $method;
            });

            self::assertNotEmpty($matches, sprintf(
                'No existe una ruta %s para %s::%s (%s).',
                $endpoint['request']['method'],
                $endpoint['controller'],
                $endpoint['controllerMethod'],
                $endpoint['id']
            ));
        }
    }

    public function test_all_migrated_admin_routes_declare_authentication_and_admin_permission_middleware(): void
    {
        $router = require dirname(__DIR__, 3) . '/routes/web.php';
        $reflection = new ReflectionClass($router);
        $routes = $reflection->getProperty('routes')->getValue($router);

        $controllers = array_values(array_unique(array_column(self::inventory()['endpoints'], 'controller')));
        $adminRoutes = array_values(array_filter(
            $routes,
            static fn (array $route): bool => in_array($route['controller'], $controllers, true)
        ));

        self::assertNotEmpty($adminRoutes);
        foreach ($adminRoutes as $route) {
            self::assertSame(
                ['auth', 'permission:admin_access'],
                $route['middleware'],
                $route['method'] . ' ' . $route['uri'],
            );
        }
    }
}
