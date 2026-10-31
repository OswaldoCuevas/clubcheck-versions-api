<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Fixtures {
    interface ExamplePort
    {
        public function value(): string;
    }

    final class ExampleAdapter implements ExamplePort
    {
        public function value(): string
        {
            return 'adapter';
        }
    }

    final readonly class ExampleConsumer
    {
        public function __construct(public ExamplePort $port)
        {
        }
    }

    final readonly class OptionalConsumer
    {
        public function __construct(public ?ExamplePort $port = null)
        {
        }
    }

    final readonly class NeedsScalar
    {
        public function __construct(public string $value)
        {
        }
    }

    final readonly class CircularA
    {
        public function __construct(public CircularB $dependency)
        {
        }
    }

    final readonly class CircularB
    {
        public function __construct(public CircularA $dependency)
        {
        }
    }
}

namespace Tests\Unit\Core {
    use Core\Container;
    use PHPUnit\Framework\TestCase;
    use RuntimeException;
    use Tests\Unit\Core\Fixtures\CircularA;
    use Tests\Unit\Core\Fixtures\ExampleAdapter;
    use Tests\Unit\Core\Fixtures\ExampleConsumer;
    use Tests\Unit\Core\Fixtures\ExamplePort;
    use Tests\Unit\Core\Fixtures\NeedsScalar;
    use Tests\Unit\Core\Fixtures\OptionalConsumer;

    final class ContainerTest extends TestCase
    {
        public function test_autowires_a_concrete_class_and_resolves_its_interface_binding(): void
        {
            $container = new Container();
            $container->singleton(ExamplePort::class, ExampleAdapter::class);

            $consumer = $container->get(ExampleConsumer::class);

            self::assertInstanceOf(ExampleConsumer::class, $consumer);
            self::assertInstanceOf(ExampleAdapter::class, $consumer->port);
            self::assertSame($consumer->port, $container->get(ExamplePort::class));
        }

        public function test_transient_binding_creates_a_new_instance_each_time(): void
        {
            $container = new Container();
            $container->bind(ExamplePort::class, ExampleAdapter::class);

            self::assertNotSame(
                $container->get(ExamplePort::class),
                $container->get(ExamplePort::class),
            );
        }

        public function test_optional_constructor_parameter_keeps_its_default_value(): void
        {
            $container = new Container();
            $container->singleton(ExamplePort::class, ExampleAdapter::class);

            $consumer = $container->get(OptionalConsumer::class);

            self::assertNull($consumer->port);
        }

        public function test_reports_a_required_scalar_that_needs_a_factory(): void
        {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('registra una factory');

            (new Container())->get(NeedsScalar::class);
        }

        public function test_detects_circular_dependencies(): void
        {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Dependencia circular detectada');

            (new Container())->get(CircularA::class);
        }
    }
}
