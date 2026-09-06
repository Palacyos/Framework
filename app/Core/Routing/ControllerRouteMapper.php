<?php
declare(strict_types=1);
namespace App\Core\Routing;

use App\Core\Http\HttpException;
use App\Core\Routing\Attributes\HttpMethodAttribute;
use App\Core\Routing\Attributes\Route;
use ReflectionAttribute;
use ReflectionClass;

/**
 * @phpstan-type Handler \Closure|array{class-string, string}
 * @phpstan-type RouteDefinition array{method: string, path: string, handler: Handler, middleware: list<string|object>, name: ?string, metadata: EndpointMetadataCollection}
 */
final class ControllerRouteMapper
{
    /**
     * @param list<class-string> $controllers
     * @return list<RouteDefinition>
     */
    public function map(array $controllers): array
    {
        $routes = [];
        $signatures = [];

        foreach ($controllers as $controller) {
            $class = new ReflectionClass($controller);
            $prefixAttribute = $class->getAttributes(Route::class)[0] ?? null;
            $prefix = $prefixAttribute?->newInstance()->path ?? '';
            $classMetadata = array_map(static fn ($attribute): object => $attribute->newInstance(), $class->getAttributes());

            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $httpAttributes = $method->getAttributes(HttpMethodAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
                foreach ($httpAttributes as $httpAttribute) {
                    $http = $httpAttribute->newInstance();
                    $path = $this->join($prefix, $http->path);
                    $signature = $http->method . ' ' . $path;
                    if (isset($signatures[$signature])) {
                        throw new HttpException(500, "Rota duplicada: {$signature}");
                    }
                    $signatures[$signature] = true;
                    $metadata = [...$classMetadata, ...array_map(static fn ($attribute): object => $attribute->newInstance(), $method->getAttributes())];
                    $routes[] = [
                        'method' => $http->method,
                        'path' => $path,
                        'handler' => [$controller, $method->getName()],
                        'middleware' => [],
                        'name' => $http->name,
                        'metadata' => new EndpointMetadataCollection($metadata),
                    ];
                }
            }
        }

        return $routes;
    }

    private function join(string $prefix, string $path): string
    {
        $segments = array_values(array_filter(
            [trim($prefix, '/'), trim($path, '/')],
            static fn (string $segment): bool => $segment !== '',
        ));
        return '/' . implode('/', $segments);
    }
}
