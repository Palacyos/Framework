<?php

namespace App\Core;

use Closure;
use ReflectionClass;
use RuntimeException;

final class Container
{
    private array $bindings = [];

    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function make(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract])($this);
        }

        if (class_exists($abstract)) {
            return $this->autowire($abstract);
        }

        throw new RuntimeException("Não foi possível resolver: {$abstract}");
    }

    private function autowire(string $class): mixed
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

            if ($type === null || $type->isBuiltin()) {
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
