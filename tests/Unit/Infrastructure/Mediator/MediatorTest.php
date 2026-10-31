<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mediator;

use App\Infrastructure\Mediator\Mediator;
use Core\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

interface MediatorExamplePort
{
    public function prefix(): string;
}

final class MediatorExampleAdapter implements MediatorExamplePort
{
    public function prefix(): string
    {
        return 'handled';
    }
}

final readonly class MediatorExampleCommand
{
    public function __construct(public string $value)
    {
    }
}

final class MediatorExampleHandler
{
    public static int $instances = 0;

    public function __construct(private readonly MediatorExamplePort $port)
    {
        self::$instances++;
    }

    public function handle(MediatorExampleCommand $command): string
    {
        return $this->port->prefix() . ':' . $command->value;
    }
}

final class MessageWithoutConvention
{
}

final class MissingHandlerCommand
{
}

final class MediatorTest extends TestCase
{
    protected function setUp(): void
    {
        MediatorExampleHandler::$instances = 0;
    }

    public function test_resolves_and_autowires_the_handler_only_when_send_is_called(): void
    {
        $container = new Container();
        $container->singleton(MediatorExamplePort::class, MediatorExampleAdapter::class);
        $mediator = new Mediator($container);

        self::assertSame(0, MediatorExampleHandler::$instances);
        self::assertSame('handled:value', $mediator->send(new MediatorExampleCommand('value')));
        self::assertSame(1, MediatorExampleHandler::$instances);
    }

    public function test_rejects_a_message_without_command_or_query_suffix(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('debe terminar en Command o Query');

        (new Mediator(new Container()))->send(new MessageWithoutConvention());
    }

    public function test_reports_a_missing_handler(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No existe el Handler');

        (new Mediator(new Container()))->send(new MissingHandlerCommand());
    }
}
