<?php
declare(strict_types=1);
namespace App\Core\Security\Authorization;
final class AuthorizationOptions
{
    /** @var array<string, AuthorizationPolicy> */
    private array $policies = [];
    public function addPolicy(string $name, callable $configure): self
    {
        $builder = new PolicyBuilder();
        $configure($builder);
        $this->policies[$name] = $builder->build();
        return $this;
    }
    public function getPolicy(string $name): ?AuthorizationPolicy { return $this->policies[$name] ?? null; }
}
