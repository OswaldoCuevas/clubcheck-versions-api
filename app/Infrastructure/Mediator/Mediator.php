<?php

declare(strict_types=1);

namespace App\Infrastructure\Mediator;

use App\Application\Contracts\IMediator;
use Core\Container;
use RuntimeException;

/**
 * Resuelve handlers por convención:
 * FooCommand -> FooHandler y FooQuery -> FooHandler, en el mismo namespace.
 */
final readonly class Mediator implements IMediator
{
    public function __construct(private Container $container)
    {
    }

    public function send(object $message): mixed
    {
        $handlerClass = $this->handlerClassFor($message::class);
        $handler = $this->container->get($handlerClass);

        if (!method_exists($handler, 'handle')) {
            throw new RuntimeException("{$handlerClass} debe declarar handle().");
        }

        return $handler->handle($message);
    }

    /** @param class-string $messageClass @return class-string */
    private function handlerClassFor(string $messageClass): string
    {
        $handlerClass = preg_replace('/(?:Command|Query)$/', 'Handler', $messageClass);

        if (!is_string($handlerClass) || $handlerClass === $messageClass) {
            throw new RuntimeException(
                "{$messageClass} debe terminar en Command o Query para enviarse al Mediator."
            );
        }

        if (!class_exists($handlerClass)) {
            throw new RuntimeException("No existe el Handler {$handlerClass} para {$messageClass}.");
        }

        return $handlerClass;
    }
}
