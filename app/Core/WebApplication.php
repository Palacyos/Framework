<?php

namespace App\Core;

use App\Core\Filters\FilterPipeline;
use App\Core\Http\HttpContext;
use App\Core\Http\HttpException;
use App\Core\Http\HttpRequest;
use App\Core\Http\HttpResponse;
use App\Core\Http\ProblemDetails;
use App\Core\Routing\ControllerRouteMapper;
use App\Core\Routing\EndpointMetadataCollection;

/**
 * @phpstan-type Handler \Closure|array{class-string, string}
 * @phpstan-type RouteDefinition array{method: string, path: string, handler: Handler, middleware: list<string|object>, name: ?string, metadata: \App\Core\Routing\EndpointMetadataCollection}
 */
final class WebApplication
{
    /** @var list<RouteDefinition> */
    private array $routes           = [];
    /** @var list<string|object> */
    private array $globalMiddleware = [];

    public function __construct(private readonly Container $container) {}

    public function use(string|object $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapGet(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapPost(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapPut(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapDelete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * @param Handler $handler
     * @param list<string|object> $middleware
     */
    public function mapPatch(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /** @param class-string|list<class-string> $controllers */
    public function mapControllers(string|array $controllers): void
    {
        $controllers = is_string($controllers) ? [$controllers] : $controllers;
        foreach ((new ControllerRouteMapper())->map($controllers) as $route) {
            $route['middleware'] = array_merge($this->globalMiddleware, $route['middleware']);
            $this->routes[] = $route;
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
    private function addRoute(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'handler'    => $handler,
            'middleware' => array_merge($this->globalMiddleware, $middleware),
            'name'       => null,
            'metadata'   => new EndpointMetadataCollection(),
        ];
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
                detail: ($_ENV['APP_ENV'] ?? 'production') === 'local' ? $exception->getMessage() : null,
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
