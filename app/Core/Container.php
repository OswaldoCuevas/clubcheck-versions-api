<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

final class Container
{
    /** @var array<class-string, callable(self): object> */
    private array $factories = [];

    /** @var array<class-string, object> */
    private array $instances = [];

    /** @param class-string $id */
    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /** @param class-string $id */
    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    /** @template T of object @param class-string<T> $id @return T */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $factory = $this->factories[$id] ?? null;
        if ($factory === null) {
            throw new RuntimeException("Servicio no registrado: {$id}");
        }

        $service = $factory($this);
        if (!$service instanceof $id) {
            throw new RuntimeException("La fábrica de {$id} devolvió " . get_debug_type($service));
        }

        return $this->instances[$id] = $service;
    }
}
