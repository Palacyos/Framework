<?php

namespace App\Core;

final class WebApplication
{
    private array $routes           = [];
    private array $globalMiddleware = [];

    public function __construct(private readonly Container $container) {}

    public function use(string|object $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function mapGet(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function mapPost(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function mapPut(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function mapDelete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function mapPatch(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'handler'    => $handler,
            'middleware' => array_merge($this->globalMiddleware, $middleware),
        ];
    }

    public function run(): void
    {
        Session::start();
        $this->container->beginScope();

        $router = new Router($this->routes, $this->container);
        $router->dispatch();
    }
}
