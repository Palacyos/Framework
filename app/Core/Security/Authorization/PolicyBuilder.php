<?php
declare(strict_types=1);
namespace App\Core\Security\Authorization;
final class PolicyBuilder
{
    private bool $authenticated = false;
    /** @var list<string> */
    private array $roles = [];
    /** @var list<array{string, list<string>}> */
    private array $claims = [];
    public function requireAuthenticatedUser(): self { $this->authenticated = true; return $this; }
    public function requireRole(string ...$roles): self { $this->roles = [...$this->roles, ...array_values($roles)]; return $this; }
    public function requireClaim(string $type, string ...$values): self { $this->claims[] = [$type, array_values($values)]; return $this; }
    public function build(): AuthorizationPolicy { return new AuthorizationPolicy($this->authenticated, array_values(array_unique($this->roles)), $this->claims); }
}
