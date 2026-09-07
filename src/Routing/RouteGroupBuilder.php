<?php

declare(strict_types=1);

namespace Palacios\Framework\Routing;

use Palacios\Framework\Security\Attributes\AllowAnonymous;
use Palacios\Framework\Security\Attributes\Authorize;
use Palacios\Framework\WebApplication;

final class RouteGroupBuilder
{
    /** @var list<string|object> */
    private array $middleware = [];
    /** @var list<object> */
    private array $metadata = [];

    public function __construct(
        private readonly WebApplication $application,
        private readonly string $prefix,
    ) {}

    public function addMiddleware(string|object $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /** @param list<string> $roles */
    public function requireAuthorization(?string $policy = null, array $roles = []): self
    {
        $this->metadata[] = new Authorize($policy, $roles);
        return $this;
    }

    public function allowAnonymous(): self
    {
        $this->metadata[] = new AllowAnonymous();
        return $this;
    }

    /** @param string|list<string> $tags */
    public function withTags(string|array $tags): self
    {
        $this->metadata[] = new EndpointTags(is_string($tags) ? [$tags] : $tags);
        return $this;
    }

    public function mapGroup(string $prefix): self
    {
        if (trim($prefix, '/') === '' || strpbrk($prefix, '?#') !== false) {
            throw new \InvalidArgumentException("Prefixo de grupo inválido: {$prefix}");
        }

        $group = new self($this->application, $this->join($prefix));
        $group->middleware = $this->middleware;
        $group->metadata = $this->metadata;
        return $group;
    }

    /** @param \Closure|array{class-string, string} $handler */
    public function mapGet(string $path, array|callable $handler): RouteHandlerBuilder { return $this->map('GET', $path, $handler); }
    /** @param \Closure|array{class-string, string} $handler */
    public function mapPost(string $path, array|callable $handler): RouteHandlerBuilder { return $this->map('POST', $path, $handler); }
    /** @param \Closure|array{class-string, string} $handler */
    public function mapPut(string $path, array|callable $handler): RouteHandlerBuilder { return $this->map('PUT', $path, $handler); }
    /** @param \Closure|array{class-string, string} $handler */
    public function mapPatch(string $path, array|callable $handler): RouteHandlerBuilder { return $this->map('PATCH', $path, $handler); }
    /** @param \Closure|array{class-string, string} $handler */
    public function mapDelete(string $path, array|callable $handler): RouteHandlerBuilder { return $this->map('DELETE', $path, $handler); }

    /** @param \Closure|array{class-string, string} $handler */
    private function map(string $method, string $path, array|callable $handler): RouteHandlerBuilder
    {
        $builder = $this->application->map($method, $this->join($path), $handler, $this->middleware);
        foreach ($this->metadata as $metadata) {
            $builder->withMetadata($metadata);
        }
        return $builder;
    }

    private function join(string $path): string
    {
        return '/' . implode('/', array_filter([
            trim($this->prefix, '/'),
            trim($path, '/'),
        ], static fn (string $part): bool => $part !== ''));
    }
}
