<?php

namespace App\Core;

use App\Core\Results\IActionResult;
use App\Core\Results\NotFoundResult;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;

final readonly class Router
{
    public function __construct(
        private array     $routes,
        private Container $container
    ) {}

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['path'], $path);

            if ($params === null) {
                continue;
            }

            $this->runPipeline($route['middleware'], $route['handler'], $params);
            return;
        }

        (new NotFoundResult())->execute();
    }

    private function runPipeline(array $middleware, array|callable $handler, array $routeParams): void
    {
        /**
         * @throws \ReflectionException
         */
        $final = function () use ($handler, $routeParams): void {
            $result = $this->invokeHandler($handler, $routeParams);
            if ($result instanceof IActionResult) {
                $result->execute();
            }
        };

        $chain = array_reduce(
            array_reverse($middleware),
            function (callable $next, string|object $mw) {
                return function () use ($next, $mw): void {
                    $instance = is_string($mw) ? $this->container->make($mw) : $mw;
                    $instance->handle($next);
                };
            },
            $final
        );

        $chain();
    }

    /**
     * @throws \ReflectionException
     */
    private function invokeHandler(array|callable $handler, array $routeParams): mixed
    {
        if (is_callable($handler) && !is_array($handler)) {
            $ref  = new ReflectionFunction($handler(...));
            $args = $this->bindParams($ref->getParameters(), $routeParams);
            return $handler(...$args);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $action] = $handler;
            $controller = $this->container->make($class);
            $ref        = new ReflectionMethod($controller, $action);
            $args       = $this->bindParams($ref->getParameters(), $routeParams);
            return $controller->$action(...$args);
        }

        throw new RuntimeException('Handler inválido: ' . json_encode($handler));
    }

    private function bindParams(array $reflectionParams, array $routeParams): array
    {
        $args = [];

        foreach ($reflectionParams as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if (array_key_exists($name, $routeParams)) {
                $value = $routeParams[$name];
                if ($type && $type->isBuiltin()) {
                    settype($value, $type->getName());
                }
                $args[] = $value;
                continue;
            }

            if ($type && !$type->isBuiltin()) {
                $args[] = $this->container->make($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new RuntimeException("Parâmetro '{$name}' não pôde ser resolvido.");
        }

        return $args;
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        if (!str_contains($routePath, '{')) {
            return $routePath === $requestPath ? [] : null;
        }

        $pattern = preg_replace('/\{([a-zA-Z_]+)}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        return array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
    }
}
