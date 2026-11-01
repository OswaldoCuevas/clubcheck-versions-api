<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Tests\Characterization\Support\DesktopHttpTestCase;

#[Group('characterization')]
final class DesktopApiControllerCharacterizationTest extends DesktopHttpTestCase
{
    public function test_inventory_matches_public_methods_and_routes(): void
    {
        $inventory = self::inventory();
        $methodsByController = [];

        foreach ($inventory['endpoints'] as $endpoint) {
            $controller = $endpoint['controller'] ?? $inventory['controller'];
            $methodsByController[$controller][] = $endpoint['controllerMethod'];
        }

        foreach ($methodsByController as $controller => $expectedMethods) {
            $reflection = new ReflectionClass($controller);
            $actualMethods = array_values(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                array_filter(
                    $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                    static fn (ReflectionMethod $method): bool => $method->getName() !== '__construct'
                        && $method->getDeclaringClass()->getName() === $controller,
                ),
            ));

            sort($actualMethods);
            sort($expectedMethods);
            self::assertSame($actualMethods, $expectedMethods, $controller);
        }

        $router = require dirname(__DIR__, 3) . '/routes/web.php';
        $routes = (new ReflectionClass($router))->getProperty('routes')->getValue($router);

        foreach ($inventory['endpoints'] as $endpoint) {
            $routeController = $endpoint['routeController'] ?? $inventory['routeController'];
            $matches = array_values(array_filter(
                $routes,
                static fn (array $route): bool => $route['controller'] === $routeController
                    && $route['action'] === $endpoint['controllerMethod']
                    && $route['method'] === $endpoint['request']['method']
                    && $route['uri'] === $endpoint['request']['path'],
            ));

            self::assertCount(1, $matches, $endpoint['id']);
            self::assertSame(
                $endpoint['routeMiddleware'],
                $matches[0]['middleware'],
                $endpoint['id'],
            );
        }
    }

    public function test_every_endpoint_preserves_its_empty_database_contract(): void
    {
        foreach (self::inventory()['endpoints'] as $endpoint) {
            $request = $endpoint['request'];
            $expected = $endpoint['expected'];
            $response = $this->requestAndRecord(
                $endpoint['id'] . '.baseline',
                $request['method'],
                $request['path'],
                $request['options'] ?? [],
                $expected,
                $endpoint['authenticated'],
            );

            self::assertSame($expected['status'], $response->getStatusCode(), $endpoint['id']);
            $json = $this->responseJson($response);

            foreach ($expected['keys'] as $key) {
                self::assertArrayHasKey($key, $json, $endpoint['id']);
            }
        }
    }

    public function test_protected_endpoints_require_a_desktop_token(): void
    {
        foreach (self::inventory()['endpoints'] as $endpoint) {
            if ($endpoint['routeMiddleware'] !== ['desktop_jwt']) {
                continue;
            }

            $request = $endpoint['request'];
            $response = $this->requestAndRecord(
                $endpoint['id'] . '.unauthorized',
                $request['method'],
                $request['path'],
                $request['options'] ?? [],
                [
                    'status' => 401,
                    'jsonSubset' => [
                        'error_code' => 'TOKEN_MISSING',
                    ],
                ],
                false,
            );

            self::assertSame(401, $response->getStatusCode());
            self::assertSame('TOKEN_MISSING', $this->responseJson($response)['error_code']);
        }
    }

    public function test_login_accepts_the_seeded_administrator(): void
    {
        $response = $this->requestAndRecord(
            'desktop.login.success',
            'POST',
            '/api/desktop/login',
            [
                'json' => [
                    'codeAccess' => 'DESKTOP-TEST',
                    'login' => 'desktop-admin',
                    'password' => 'desktop-password',
                ],
            ],
            [
                'status' => 200,
                'jsonSubset' => [
                    'status' => 'success',
                    'expiresIn' => 1296000,
                    'customer' => [
                        'customerId' => $this->customerId,
                    ],
                    'administrator' => [
                        'id' => $this->adminId,
                        'username' => 'desktop-admin',
                        'role' => 2,
                    ],
                ],
            ],
            false,
        );

        $json = $this->responseJson($response);
        self::assertSame('success', $json['status']);
        self::assertNotSame('', $json['token']);
    }

    public function test_router_enforces_desktop_endpoint_methods(): void
    {
        $login = $this->requestAndRecord(
            'desktop.methods.login.get',
            'GET',
            '/api/desktop/login',
            [],
            ['status' => 405],
            false,
        );
        $dashboard = $this->requestAndRecord(
            'desktop.methods.dashboard.post',
            'POST',
            '/api/desktop/dashboard',
            [],
            ['status' => 405],
            false,
        );

        self::assertSame(405, $login->getStatusCode());
        self::assertSame(405, $dashboard->getStatusCode());
    }
}
