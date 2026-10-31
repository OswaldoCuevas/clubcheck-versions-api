<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class CustomersControllerContractInventoryTest extends TestCase
{
    public function test_every_public_action_is_listed_once_in_the_characterization_contract(): void
    {
        $inventoryPath = dirname(__DIR__, 2)
            . '/Characterization/contracts/customers-controller.json';
        $inventory = json_decode(
            (string) file_get_contents($inventoryPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $methodsByController = [];

        foreach ($inventory['endpoints'] as $endpoint) {
            $controllerClass = $endpoint['controller'] ?? $inventory['controller'];
            $methodsByController[$controllerClass][] = $endpoint['controllerMethod'];
        }

        foreach ($methodsByController as $controllerClass => $contractMethods) {
            $reflection = new ReflectionClass($controllerClass);
            $actualMethods = array_values(array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                array_filter(
                    $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                    static fn (ReflectionMethod $method): bool => $method->getName() !== '__construct'
                        && $method->getDeclaringClass()->getName() === $controllerClass,
                ),
            ));
            $uniqueContractMethods = array_values(array_unique($contractMethods));

            sort($actualMethods);
            sort($contractMethods);
            sort($uniqueContractMethods);

            self::assertSame(
                $contractMethods,
                $uniqueContractMethods,
                "Hay acciones duplicadas en el contrato de {$controllerClass}.",
            );
            self::assertSame($actualMethods, $contractMethods, $controllerClass);
        }
    }

    public function test_each_contract_case_has_request_and_expected_response_metadata(): void
    {
        $inventoryPath = dirname(__DIR__, 2)
            . '/Characterization/contracts/customers-controller.json';
        $inventory = json_decode(
            (string) file_get_contents($inventoryPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($inventory['endpoints'] as $endpoint) {
            self::assertNotSame('', $endpoint['id']);
            self::assertNotSame('', $endpoint['name']);
            self::assertContains($endpoint['request']['method'], ['GET', 'POST', 'PATCH']);
            self::assertStringStartsWith('/api/customers', $endpoint['request']['path']);
            self::assertIsInt($endpoint['expected']['status']);
            self::assertContains($endpoint['routeMiddleware'], [[], ['customer_jwt']]);
        }
    }
}
