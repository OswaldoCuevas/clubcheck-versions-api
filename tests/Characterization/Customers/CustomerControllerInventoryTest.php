<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerControllerInventoryTest extends CustomerHttpTestCase
{
    protected function shouldResetFixtures(): bool
    {
        return false;
    }

    protected function shouldPrepareAuthenticatedCustomer(): bool
    {
        return false;
    }

    public function test_every_public_controller_method_has_a_versioned_http_contract(): void
    {
        $inventory = self::inventory();
        $methodsByController = [];

        foreach ($inventory['endpoints'] as $endpoint) {
            $controllerClass = $endpoint['controller'] ?? $inventory['controller'];
            $methodsByController[$controllerClass][] = $endpoint['controllerMethod'];
        }

        foreach ($methodsByController as $controllerClass => $inventoriedMethods) {
            $reflection = new ReflectionClass($controllerClass);
            $actualMethods = array_values(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                array_filter(
                    $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                    static fn (ReflectionMethod $method): bool => $method->getName() !== '__construct'
                        && $method->getDeclaringClass()->getName() === $controllerClass,
                ),
            ));

            sort($actualMethods);
            sort($inventoriedMethods);

            self::assertSame($actualMethods, $inventoriedMethods, $controllerClass);
        }
    }

    public function test_inventory_matches_router_methods_paths_and_middleware(): void
    {
        $router = require dirname(__DIR__, 3) . '/routes/web.php';
        $reflection = new ReflectionClass($router);
        $routes = $reflection->getProperty('routes')->getValue($router);

        $inventory = self::inventory();

        foreach ($inventory['endpoints'] as $endpoint) {
            $routeController = $endpoint['routeController'] ?? $inventory['routeController'];
            $matches = array_values(array_filter(
                $routes,
                static function (array $route) use ($endpoint, $routeController): bool {
                    $methodMatches = $route['method'] === 'ANY'
                        || $route['method'] === $endpoint['request']['method'];

                    return $route['controller'] === $routeController
                        && $route['action'] === $endpoint['controllerMethod']
                        && $methodMatches;
                },
            ));

            self::assertCount(1, $matches, $endpoint['id']);
            self::assertSame(
                $endpoint['routeMiddleware'],
                $matches[0]['middleware'],
                $endpoint['id'],
            );
            self::assertSame(
                $this->routeTemplate($endpoint['request']['path']),
                $matches[0]['uri'],
                $endpoint['id'],
            );
        }
    }

    private function routeTemplate(string $path): string
    {
        return match ($path) {
            '/api/customers/customer-test-1' => '/api/customers/:customerId',
            '/api/customers/messages-sends-at-month/customer-test-1'
                => '/api/customers/messages-sends-at-month/:customerId',
            default => $path,
        };
    }
}
