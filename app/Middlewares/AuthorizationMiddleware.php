<?php
declare(strict_types=1);
namespace App\Middlewares;

use App\Core\Http\HttpContext;
use App\Core\Http\HttpException;
use App\Core\Routing\EndpointMetadataCollection;
use App\Core\Security\Attributes\AllowAnonymous;
use App\Core\Security\Attributes\Authorize;
use App\Core\Security\Authorization\AuthorizationOptions;
use App\Core\Security\Authorization\AuthorizationPolicy;
use App\Core\Security\Authorization\AuthorizationService;

final readonly class AuthorizationMiddleware
{
    public function __construct(
        private AuthorizationOptions $options = new AuthorizationOptions(),
        private AuthorizationService $authorization = new AuthorizationService(),
    ) {}

    public function handle(HttpContext $context, callable $next): void
    {
        $metadata = $context->endpoint['metadata'] ?? new EndpointMetadataCollection();
        if ($metadata->has(AllowAnonymous::class)) { $next(); return; }

        $authorize = array_values(array_filter($metadata->all(), static fn (object $item): bool => $item instanceof Authorize));
        if ($authorize === []) { $next(); return; }

        if (!$context->user->identity()->isAuthenticated()) {
            throw new HttpException(401, 'Autenticação necessária.');
        }

        foreach ($authorize as $requirement) {
            $policy = $requirement->policy !== null
                ? $this->options->getPolicy($requirement->policy) ?? throw new HttpException(500, "Policy não registrada: {$requirement->policy}")
                : new AuthorizationPolicy(true, $requirement->roles);
            if (!$this->authorization->authorize($context->user, $policy)) {
                throw new HttpException(403, 'Acesso negado.');
            }
        }

        $next();
    }
}
