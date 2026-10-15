<?php

declare(strict_types=1);

use Palacios\Framework\Container;
use Palacios\Framework\ServiceCollection;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testUsesObjectDefaultWhenInterfaceHasNoRegistration(): void
    {
        $service = (new Container())->make(ContainerOptionalService::class);

        self::assertInstanceOf(ContainerOptionalService::class, $service);
        self::assertInstanceOf(ContainerDefaultDependency::class, $service->dependency);
    }

    public function testExplicitRegistrationOverridesObjectDefault(): void
    {
        $services = (new ServiceCollection())
            ->addSingleton(ContainerTestDependency::class, ContainerCustomDependency::class);
        $container = new Container($services->getBindings());

        $service = $container->make(ContainerOptionalService::class);

        self::assertInstanceOf(ContainerOptionalService::class, $service);
        self::assertInstanceOf(ContainerCustomDependency::class, $service->dependency);
    }

    public function testRequiredInterfaceWithoutRegistrationStillFails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Não foi possível resolver: ' . ContainerTestDependency::class);

        (new Container())->make(ContainerRequiredService::class);
    }

    public function testRegisteredFactoryFailureDoesNotFallBackToDefault(): void
    {
        $services = (new ServiceCollection())
            ->addSingleton(
                ContainerTestDependency::class,
                static function (): ContainerTestDependency {
                    throw new LogicException('Registered factory failure');
                },
            );
        $container = new Container($services->getBindings());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Registered factory failure');

        $container->make(ContainerOptionalService::class);
    }

    public function testUsesNullDefaultForUnregisteredNullableInterface(): void
    {
        $service = (new Container())->make(ContainerNullableService::class);

        self::assertInstanceOf(ContainerNullableService::class, $service);
        self::assertNull($service->dependency);
    }

    public function testObjectDefaultWinsOverConcreteAutowiringWithoutRegistration(): void
    {
        $service = (new Container())->make(ContainerConcreteDefaultService::class);

        self::assertInstanceOf(ContainerConcreteDefaultService::class, $service);
        self::assertSame('constructor default', $service->dependency->source);
    }
}

interface ContainerTestDependency {}

final class ContainerDefaultDependency implements ContainerTestDependency {}

final class ContainerCustomDependency implements ContainerTestDependency {}

final readonly class ContainerOptionalService
{
    public function __construct(
        public ContainerTestDependency $dependency = new ContainerDefaultDependency(),
    ) {}
}

final readonly class ContainerRequiredService
{
    public function __construct(public ContainerTestDependency $dependency) {}
}

final readonly class ContainerNullableService
{
    public function __construct(public ?ContainerTestDependency $dependency = null) {}
}

final readonly class ContainerConcreteDependency
{
    public function __construct(public string $source = 'autowired') {}
}

final readonly class ContainerConcreteDefaultService
{
    public function __construct(
        public ContainerConcreteDependency $dependency = new ContainerConcreteDependency('constructor default'),
    ) {}
}
