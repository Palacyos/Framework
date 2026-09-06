<?php
declare(strict_types=1);
namespace App\Core\Security\Authorization;
final readonly class AuthorizationPolicy
{
    /**
     * @param list<string> $roles
     * @param list<array{string, list<string>}> $claims
     */
    public function __construct(public bool $requiresAuthenticatedUser = true, public array $roles = [], public array $claims = []) {}
}
