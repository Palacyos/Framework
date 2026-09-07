<?php

declare(strict_types=1);

namespace Palacios\Framework\Routing;

use Palacios\Framework\Security\Attributes\AllowAnonymous;
use Palacios\Framework\Security\Attributes\Authorize;
use Palacios\Framework\WebApplication;

final readonly class RouteHandlerBuilder
{
    public function __construct(
        private WebApplication $application,
        private int $routeIndex,
    ) {}

    public function withName(string $name): self
    {
        $this->application->setRouteName($this->routeIndex, $name);
        return $this;
    }

    public function addMiddleware(string|object $middleware): self
    {
        $this->application->addRouteMiddleware($this->routeIndex, $middleware);
        return $this;
    }

    /** @param list<string> $roles */
    public function requireAuthorization(?string $policy = null, array $roles = []): self
    {
        return $this->withMetadata(new Authorize($policy, $roles));
    }

    public function allowAnonymous(): self
    {
        return $this->withMetadata(new AllowAnonymous());
    }

    /** @param string|list<string> $tags */
    public function withTags(string|array $tags): self
    {
        return $this->withMetadata(new EndpointTags(is_string($tags) ? [$tags] : $tags));
    }

    public function withSummary(string $summary): self
    {
        return $this->withMetadata(new EndpointSummary($summary));
    }

    public function produces(int $statusCode, ?string $contentType = null): self
    {
        return $this->withMetadata(new ProducesResponse($statusCode, $contentType));
    }

    public function withMetadata(object $metadata): self
    {
        $this->application->addRouteMetadata($this->routeIndex, $metadata);
        return $this;
    }
}
