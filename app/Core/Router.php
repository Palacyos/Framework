<?php

namespace App\Core;

use App\Core\Binding\ModelBinder;
use App\Core\Filters\FilterPipeline;
use App\Core\Http\HttpContext;
use App\Core\Http\HttpException;
use BackedEnum;

use App\Core\Results\IActionResult;
use App\Core\Results\NotFoundResult;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;

/**
 * @phpstan-type Handler \Closure|array{class-string, string}
 * @phpstan-type RouteDefinition array{method: string, path: string, handler: Handler, middleware: list<string|object>, name: ?string, metadata: \App\Core\Routing\EndpointMetadataCollection}
 */
final readonly class Router
{
    /** @param list<RouteDefinition> $routes */
    public function __construct(
        private array     $routes,
        private Container $container
    ) {}

    public function dispatch(HttpContext $context): void
    {
        $method = $context->request->method;
        $path   = $context->request->path;

        $allowedMethods = [];
        foreach ($this->routes as $route) {
            $params = $this->match($route['path'], $path);
            if ($params === null) continue;

            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $context->routeValues = $params;
            $context->endpoint = $route;
            $this->runPipeline($context, $route['middleware'], $route['handler'], $params);
            return;
        }

        if ($allowedMethods !== []) {
            $context->response->header('Allow', implode(', ', array_unique($allowedMethods)));
            throw new HttpException(405, 'Método HTTP não permitido.');
        }

        (new NotFoundResult())->execute($context);
    }

    /**
     * @param list<string|object> $middleware
     * @param Handler $handler
     * @param array<string, string> $routeParams
     */
    private function runPipeline(HttpContext $context, array $middleware, array|callable $handler, array $routeParams): void
    {
        /**
         * @throws \ReflectionException
         */
        $final = function () use ($handler, $routeParams, $context): void {
            $result = $this->invokeHandler($handler, $routeParams, $context);
            if ($result instanceof IActionResult) {
                (new FilterPipeline($this->container))->invokeResult($context, $result);
            }
        };

        $chain = array_reduce(
            array_reverse($middleware),
            function (callable $next, string|object $mw) use ($context) {
                return function () use ($next, $mw, $context): void {
                    $instance = is_string($mw) ? $this->container->make($mw) : $mw;
                    if (!is_object($instance) || !method_exists($instance, 'handle')) {
                        throw new RuntimeException('Middleware inválido.');
                    }
                    $instance->handle($context, $next);
                };
            },
            $final
        );

        $chain();
    }

    /**
     * @throws \ReflectionException
     */
    /**
     * @param Handler $handler
     * @param array<string, string> $routeParams
     */
    private function invokeHandler(array|callable $handler, array $routeParams, HttpContext $context): mixed
    {
        if (is_callable($handler) && !is_array($handler)) {
            $ref  = new ReflectionFunction($handler(...));
            $args = $this->bindParams($ref->getParameters(), $context);
            return (new FilterPipeline($this->container))->invokeAction(
                $context,
                $args,
                static fn (): mixed => $handler(...$args),
            );
        }

        [$class, $action] = $handler;
        $controller = $this->container->make($class);
        if (!is_object($controller)) {
            throw new RuntimeException("Controller inválido: {$class}");
        }
        $ref = new ReflectionMethod($controller, $action);
        $args = $this->bindParams($ref->getParameters(), $context);
        return (new FilterPipeline($this->container))->invokeAction(
            $context,
            $args,
            static fn (): mixed => $controller->$action(...$args),
        );
    }

    /**
     * @param list<\ReflectionParameter> $reflectionParams
     * @return list<mixed>
     */
    private function bindParams(array $reflectionParams, HttpContext $context): array
    {
        $binder = new ModelBinder();
        return array_map(
            static fn (\ReflectionParameter $parameter): mixed => $binder->bind($parameter, $context),
            $reflectionParams,
        );

    }

    private function convertBuiltin(string $value, string $type, string $name): mixed
    {
        return match ($type) {
            'string', 'mixed' => $value,
            'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                ?? throw new HttpException(400, "Parâmetro '{$name}' deve ser inteiro."),
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
                ?? throw new HttpException(400, "Parâmetro '{$name}' deve ser numérico."),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                ?? throw new HttpException(400, "Parâmetro '{$name}' deve ser booleano."),
            default => throw new HttpException(400, "Tipo '{$type}' não suportado no binding."),
        };
    }

    private function convertObject(string $value, string $class, string $name): mixed
    {
        if (is_subclass_of($class, BackedEnum::class)) {
            return $class::tryFrom($value)
                ?? throw new HttpException(400, "Valor inválido para '{$name}'.");
        }
        return $value;
    }

    /** @return array<string, string>|null */
    private function match(string $routePath, string $requestPath): ?array
    {
        if (!str_contains($routePath, '{')) {
            return $routePath === $requestPath ? [] : null;
        }

        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::(int|uuid|alpha|slug))?}/',
            static function (array $match): string {
                $valuePattern = match ($match[2] ?? null) {
                    'int' => '-?\\d+',
                    'uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}',
                    'alpha' => '[a-zA-Z]+',
                    'slug' => '[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*',
                    default => '[^/]+',
                };
                return '(?P<' . $match[1] . '>' . $valuePattern . ')';
            },
            $routePath,
        );
        $pattern = '#^' . $pattern . '$#D';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        return array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
    }
}
