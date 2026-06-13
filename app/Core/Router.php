<?php

namespace App\Core;

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

        foreach ($this->routes as $r) {
            [$m, $p, $h, $mw] = array_pad($r, 4, []);

            if ($m !== $method) {
                continue;
            }

            $params = $this->match($p, $path);

            if ($params === null) {
                continue;
            }

            foreach ($mw as $middleware) {
                $instance = is_string($middleware)
                    ? $this->container->make($middleware)
                    : $middleware;
                $instance->handle();
            }

            if (is_callable($h)) {
                $h($params);
            } elseif (is_array($h) && count($h) === 2) {
                [$class, $action] = $h;
                $controller = $this->container->make($class);
                $controller->$action($params);
            } else {
                throw new RuntimeException("Controlador inválido: " . json_encode($h));
            }

            return;
        }

        http_response_code(404);
        echo '404';
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