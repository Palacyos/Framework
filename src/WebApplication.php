<?php

namespace Palacios\Framework;

use Palacios\Framework\Filters\FilterPipeline;
use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Http\HttpException;
use Palacios\Framework\Http\HttpRequest;
use Palacios\Framework\Http\HttpResponse;
use Palacios\Framework\Hosting\ApplicationEnvironment;
use Palacios\Framework\Http\ProblemDetails;
use Palacios\Framework\Middleware\AuthenticationMiddleware;
use Palacios\Framework\Middleware\AuthorizationMiddleware;
use Palacios\Framework\Middleware\CsrfMiddleware;
use Palacios\Framework\Middleware\RequestLoggingMiddleware;
use Palacios\Framework\Routing\ControllerRouteMapper;
use Palacios\Framework\Routing\EndpointMetadataCollection;
use Palacios\Framework\Routing\RouteGroupBuilder;
use Palacios\Framework\Routing\RouteHandlerBuilder;

/**
 * @phpstan-type Handler \Closure|array{class-string, string}
 * @phpstan-type RouteDefinition array{method: string, path: string, handler: Handler, middleware: list<string|object>, name: ?string, metadata: \Palacios\Framework\Routing\EndpointMetadataCollection}
 */
final class WebApplication
{
    /** @var list<RouteDefinition> */
    private array $routes           = [];
    /** @var list<string|object> */
    private array $globalMiddleware = [];
    private bool $includeExceptionDetails = false;

    /** @param list<class-string> $discoveredControllers */
    public function __construct(
        private readonly Container $container,
        private readonly array $discoveredControllers = [],
        public readonly ApplicationEnvironment $environment = new ApplicationEnvironment(),
    ) {}

    public function use(string|object $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    public function useExceptionHandler(): self
    {
        $this->includeExceptionDetails = false;
        return $this;
    }

    public function useDevelopmentExceptionPage(): self
    {
        if (!$this->environment->isDevelopment()) {
            throw new \LogicException('A página detalhada de exceção só pode ser usada em Development.');
        }

        $this->includeExceptionDetails = true;
        return $this;
    }
    public function useAuthentication(): self
    {
        return $this->use(AuthenticationMiddleware::class);
    }

    public function useAuthorization(): self
    {
        return $this->use(AuthorizationMiddleware::class);
    }

    public function useAntiforgery(): self
    {
        return $this->use(CsrfMiddleware::class);
    }

    public function useRequestLogging(): self
    {
        return $this->use(RequestLoggingMiddleware::class);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapGet(string $path, array|callable $handler, array $middleware = []): RouteHandlerBuilder
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapPost(string $path, array|callable $handler, array $middleware = []): RouteHandlerBuilder
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapPut(string $path, array|callable $handler, array $middleware = []): RouteHandlerBuilder
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapDelete(string $path, array|callable $handler, array $middleware = []): RouteHandlerBuilder
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapPatch(string $path, array|callable $handler, array $middleware = []): RouteHandlerBuilder
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /** @param class-string|list<class-string>|null $controllers */
    public function mapControllers(string|array|null $controllers = null): void
    {
        $controllers ??= $this->discoveredControllers;
        $controllers = is_string($controllers) ? [$controllers] : $controllers;
        foreach ((new ControllerRouteMapper())->map($controllers) as $route) {
            $route['middleware'] = array_merge($this->globalMiddleware, $route['middleware']);
            $this->registerRoute($route);
        }
    }

    /** @param array<string, scalar> $values */
    public function urlFor(string $name, array $values = []): string
    {
        foreach ($this->routes as $route) {
            if (($route['name'] ?? null) !== $name) continue;
            return preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?}/', static function (array $match) use ($values): string {
                if (!array_key_exists($match[1], $values)) throw new \InvalidArgumentException("Valor ausente para rota: {$match[1]}");
                return rawurlencode((string) $values[$match[1]]);
            }, $route['path']) ?? throw new \RuntimeException('Falha ao gerar URL da rota.');
        }
        throw new \InvalidArgumentException("Rota nomeada não encontrada: {$name}");
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    private function addRoute(string $method, string $path, array|callable $handler, array $middleware): RouteHandlerBuilder
    {
        return $this->map($method, $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function map(string $method, string $path, array|callable $handler, array $middleware = []): RouteHandlerBuilder
    {
        $path = $this->normalizePath($path);
        $index = $this->registerRoute([
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => array_merge($this->globalMiddleware, $middleware),
            'name' => null,
            'metadata' => new EndpointMetadataCollection(),
        ]);
        return new RouteHandlerBuilder($this, $index);
    }

    public function mapGroup(string $prefix): RouteGroupBuilder
    {
        if (trim($prefix, '/') === '' || strpbrk($prefix, '?#') !== false) {
            throw new \InvalidArgumentException("Prefixo de grupo inválido: {$prefix}");
        }
        return new RouteGroupBuilder($this, $this->normalizePath($prefix));
    }

    public function setRouteName(int $index, string $name): void
    {
        if ($name === '') throw new \InvalidArgumentException('O nome da rota não pode ser vazio.');
        foreach ($this->routes as $routeIndex => $route) {
            if ($routeIndex !== $index && $route['name'] === $name) {
                throw new \InvalidArgumentException("Nome de rota duplicado: {$name}");
            }
        }
        $this->routes[$index]['name'] = $name;
    }

    public function addRouteMiddleware(int $index, string|object $middleware): void
    {
        $this->routes[$index]['middleware'][] = $middleware;
    }

    public function addRouteMetadata(int $index, object $metadata): void
    {
        $items = $this->routes[$index]['metadata']->all();
        $items[] = $metadata;
        $this->routes[$index]['metadata'] = new EndpointMetadataCollection($items);
    }

    /** @param RouteDefinition $route */
    private function registerRoute(array $route): int
    {
        foreach ($this->routes as $existing) {
            if ($existing['method'] === $route['method'] && $existing['path'] === $route['path']) {
                throw new \InvalidArgumentException("Rota duplicada: {$route['method']} {$route['path']}");
            }
            if ($route['name'] !== null && $existing['name'] === $route['name']) {
                throw new \InvalidArgumentException("Nome de rota duplicado: {$route['name']}");
            }
        }
        $this->routes[] = $route;
        return count($this->routes) - 1;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        $this->container->beginScope();
        $context = HttpContext::create($request, $this->container);
        $router = new Router($this->routes, $this->container);

        try {
            $router->dispatch($context);
        } catch (\Throwable $exception) {
            $filters = new FilterPipeline($context->services);
            $filteredResult = $filters->handleException($context, $exception);
            if ($filteredResult !== null) {
                $filters->invokeResult($context, $filteredResult);
                return $context->response;
            }

            $status = $exception instanceof HttpException ? $exception->statusCode : 500;
            $problem = new ProblemDetails(
                status: $status,
                title: $status === 500 ? 'Erro interno do servidor' : $exception->getMessage(),
                detail: $this->includeExceptionDetails ? $exception->getMessage() : null,
                traceId: $context->traceId,
                errors: $exception instanceof HttpException ? $exception->errors : [],
            );
            $context->response
                ->status($status)
                ->header('Content-Type', 'application/problem+json; charset=utf-8')
                ->write(json_encode($problem, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        }

        return $context->response;
    }

    public function run(): void
    {
        Session::start();
        $this->handle(HttpRequest::capture())->send();
    }
}
