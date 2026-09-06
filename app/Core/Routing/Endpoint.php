<?php
declare(strict_types=1);
namespace App\Core\Routing;
final readonly class Endpoint
{
    /** @param list<string|object> $middleware */
    public function __construct(
        public string $method,
        public string $path,
        public mixed $handler,
        public array $middleware = [],
        public ?string $name = null,
        public EndpointMetadataCollection $metadata = new EndpointMetadataCollection(),
    ) {}
}
