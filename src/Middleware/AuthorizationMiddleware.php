<?php

declare(strict_types=1);

namespace Palacios\Framework\Middleware;

use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Http\HttpException;
use Palacios\Framework\Routing\EndpointMetadataCollection;
use Palacios\Framework\Security\Attributes\AllowAnonymous;
use Palacios\Framework\Security\Attributes\Authorize;
use Palacios\Framework\Security\Authentication\AuthenticationFailureHandler;
use Palacios\Framework\Security\Authentication\DefaultAuthenticationFailureHandler;
use Palacios\Framework\Security\Authorization\AuthorizationOptions;
use Palacios\Framework\Security\Authorization\AuthorizationPolicy;
use Palacios\Framework\Security\Authorization\AuthorizationService;

final readonly class AuthorizationMiddleware
{
    public function __construct(
        private AuthorizationOptions $options = new AuthorizationOptions(),
        private AuthorizationService $authorization = new AuthorizationService(),
        private AuthenticationFailureHandler $failureHandler = new DefaultAuthenticationFailureHandler(),
    ) {}

    public function handle(HttpContext $context, callable $next): void
    {
        $metadata = $context->endpoint['metadata'] ?? new EndpointMetadataCollection();
        if ($metadata->has(AllowAnonymous::class)) {
            $next();
            return;
        }

        $authorize = array_values(array_filter(
            $metadata->all(),
            static fn (object $item): bool => $item instanceof Authorize,
        ));
        if ($authorize === []) {
            $next();
            return;
        }

        if (!$context->user->identity()->isAuthenticated()) {
            $this->failureHandler->challenge($context);
            return;
        }

        foreach ($authorize as $requirement) {
            $policy = $requirement->policy !== null
                ? $this->options->getPolicy($requirement->policy)
                    ?? throw new HttpException(500, "Policy não registrada: {$requirement->policy}")
                : new AuthorizationPolicy(true, $requirement->roles);

            if (!$this->authorization->authorize($context->user, $policy)) {
                $this->failureHandler->forbid($context);
                return;
            }
        }

        $next();
    }
}
