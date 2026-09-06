<?php

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/** @phpstan-type Binding array{concrete: string|Closure, lifetime: 'singleton'|'scoped'|'transient'} */
final class Container
{
    /** @var array<string, mixed> */
    private array $singletons = [];
    /** @var array<string, mixed> */
    private array $scoped     = [];
    /** @var array<class-string, true> */
    private array $resolving  = [];

    /** @param array<string, Binding> $bindings */
    public function __construct(private array $bindings = []) {}

    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = ['concrete' => $factory, 'lifetime' => 'transient'];
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }

    public function make(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            ['concrete' => $concrete, 'lifetime' => $lifetime] = $this->bindings[$abstract];

            return match ($lifetime) {
                'singleton' => $this->resolveSingleton($abstract, $concrete),
                'scoped'    => $this->resolveScoped($abstract, $concrete),
                default     => $this->resolve($concrete),
            };
        }

        if (class_exists($abstract)) {
            return $this->autowire($abstract);
        }

        throw new RuntimeException("Não foi possível resolver: {$abstract}");
    }

    public function beginScope(): void
    {
        $this->scoped = [];
    }

    private function resolveSingleton(string $abstract, string|Closure $concrete): mixed
    {
        return $this->singletons[$abstract] ??= $this->resolve($concrete);
    }

    private function resolveScoped(string $abstract, string|Closure $concrete): mixed
    {
        return $this->scoped[$abstract] ??= $this->resolve($concrete);
    }

    private function resolve(string|Closure $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }
        if (!class_exists($concrete)) {
            throw new RuntimeException("Classe concreta não encontrada: {$concrete}");
        }
        return $this->autowire($concrete);
    }

    /**
     * @throws \ReflectionException
     */
    /** @param class-string $class */
    private function autowire(string $class): mixed
    {
        if (isset($this->resolving[$class])) {
            $chain = implode(' -> ', [...array_keys($this->resolving), $class]);
            throw new RuntimeException("Dependência circular detectada: {$chain}");
        }

        $this->resolving[$class] = true;
        try {
            return $this->build($class);
        } finally {
            unset($this->resolving[$class]);
        }
    }

    /** @param class-string $class */
    private function build(string $class): mixed
    {
        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Classe não instanciável: {$class}");
        }

        $constructor = $ref->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }
                throw new RuntimeException(
                    "Parâmetro '{$param->getName()}' em {$class} não pode ser resolvido automaticamente."
                );
            }

            $args[] = $this->make($type->getName());
        }

        return $ref->newInstanceArgs($args);
    }
}
