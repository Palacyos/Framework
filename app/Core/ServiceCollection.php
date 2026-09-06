<?php

namespace App\Core;

use Closure;

/** @phpstan-type Binding array{concrete: string|Closure, lifetime: 'singleton'|'scoped'|'transient'} */
final class ServiceCollection
{
    /** @var array<string, Binding> */
    private array $bindings = [];

    public function addSingleton(string $abstract, string|Closure $concrete): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'lifetime' => 'singleton'];
    }

    public function addScoped(string $abstract, string|Closure $concrete): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'lifetime' => 'scoped'];
    }

    public function addTransient(string $abstract, string|Closure $concrete): void
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'lifetime' => 'transient'];
    }

    /** @return array<string, Binding> */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
