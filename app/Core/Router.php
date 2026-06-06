<?php

namespace App\Core;

use RuntimeException;

final class Router
{
    public function __construct(
        private array $routes,
        private Container $container
    ) {}

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $r) {
            [$m, $p, $h, $mw] = array_pad($r, 4, []);

            if ($m !== $method || $p !== $path) {
                continue;
            }

            foreach ($mw as $middleware) {
                $instance = is_string($middleware)
                    ? $this->container->make($middleware)
                    : $middleware;
                $instance->handle();
            }

            if (is_callable($h)) {
                $h();
            } elseif (is_array($h) && count($h) === 2) {
                [$class, $action] = $h;
                $controller = $this->container->make($class);
                $controller->$action();
            } else {
                throw new RuntimeException("Handler inválido: " . json_encode($h));
            }

            return;
        }

        http_response_code(404);
        echo '404';
    }
}
