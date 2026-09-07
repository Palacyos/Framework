<?php
declare(strict_types=1);
namespace Palacios\Framework\Security\Authorization;
use Palacios\Framework\Security\Authentication\ClaimsPrincipal;
final readonly class AuthorizationService
{
    public function authorize(ClaimsPrincipal $user, AuthorizationPolicy $policy): bool
    {
        if ($policy->requiresAuthenticatedUser && !$user->identity()->isAuthenticated()) return false;
        if ($policy->roles !== [] && !array_any($policy->roles, $user->isInRole(...))) return false;
        foreach ($policy->claims as [$type, $values]) {
            if ($values === [] ? !$user->hasClaim($type) : !array_any($values, fn (string $value): bool => $user->hasClaim($type, $value))) return false;
        }
        return true;
    }
}
