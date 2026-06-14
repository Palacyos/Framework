<?php

namespace App\Core;

use Closure;

final class ServiceCollection
{
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

    public function getBindings(): array
    {
        return $this->bindings;
    }
}
