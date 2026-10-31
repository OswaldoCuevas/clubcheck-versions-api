<?php

declare(strict_types=1);

namespace Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Contenedor pequeño con bindings y autowiring por constructor.
 *
 * Las interfaces requieren un binding explícito. Las clases concretas se
 * construyen automáticamente resolviendo sus dependencias obligatorias.
 */
final class Container
{
    /** @var array<class-string, callable(self): object|class-string> */
    private array $singletons = [];

    /** @var array<class-string, callable(self): object|class-string> */
    private array $bindings = [];

    /** @var array<class-string, object> */
    private array $instances = [];

    /** @var array<class-string, true> */
    private array $resolving = [];

    /**
     * Registra una instancia compartida durante la vida de este Container.
     *
     * @param class-string $id
     * @param callable(self): object|class-string $concrete
     */
    public function singleton(string $id, callable|string $concrete): void
    {
        $this->singletons[$id] = $concrete;
        unset($this->bindings[$id], $this->instances[$id]);
    }

    /**
     * Registra una resolución nueva cada vez que se solicita el contrato.
     *
     * @param class-string $id
     * @param callable(self): object|class-string $concrete
     */
    public function bind(string $id, callable|string $concrete): void
    {
        $this->bindings[$id] = $concrete;
        unset($this->singletons[$id], $this->instances[$id]);
    }

    /** @param class-string $id */
    public function has(string $id): bool
    {
        return $id === self::class
            || isset($this->instances[$id])
            || isset($this->singletons[$id])
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    /** @template T of object @param class-string<T> $id @return T */
    public function get(string $id): object
    {
        if ($id === self::class) {
            return $this;
        }

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            $chain = implode(' -> ', [...array_keys($this->resolving), $id]);
            throw new RuntimeException("Dependencia circular detectada: {$chain}");
        }

        $this->resolving[$id] = true;

        try {
            if (isset($this->singletons[$id])) {
                $service = $this->resolveDefinition($this->singletons[$id]);
                $this->assertMatches($id, $service);
                return $this->instances[$id] = $service;
            }

            if (isset($this->bindings[$id])) {
                $service = $this->resolveDefinition($this->bindings[$id]);
                $this->assertMatches($id, $service);
                return $service;
            }

            return $this->autowire($id);
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /** @param callable(self): object|class-string $definition */
    private function resolveDefinition(callable|string $definition): object
    {
        if (is_string($definition)) {
            return $this->get($definition);
        }

        return $definition($this);
    }

    /** @param class-string $className */
    private function autowire(string $className): object
    {
        if (!class_exists($className)) {
            throw new RuntimeException(
                "No se puede resolver {$className}: registra una implementación para este contrato."
            );
        }

        $reflection = new ReflectionClass($className);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("No se puede instanciar {$className}.");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            // Los parámetros opcionales conservan el comportamiento heredado.
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new RuntimeException(sprintf(
                    'No se puede resolver $%s de %s::__construct(); registra una factory.',
                    $parameter->getName(),
                    $className,
                ));
            }

            $arguments[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($arguments);
    }

    /** @param class-string $id */
    private function assertMatches(string $id, object $service): void
    {
        if (!$service instanceof $id) {
            throw new RuntimeException(
                "La definición de {$id} devolvió " . get_debug_type($service)
            );
        }
    }
}
