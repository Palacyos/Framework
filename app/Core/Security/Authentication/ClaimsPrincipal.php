<?php
declare(strict_types=1);
namespace App\Core\Security\Authentication;
final readonly class ClaimsPrincipal
{
    /** @param list<ClaimsIdentity> $identities */
    public function __construct(private array $identities = []) {}
    public static function anonymous(): self { return new self([new ClaimsIdentity()]); }
    public function identity(): ClaimsIdentity
    {
        foreach ($this->identities as $identity) if ($identity->isAuthenticated()) return $identity;
        return $this->identities[0] ?? new ClaimsIdentity();
    }
    public function isInRole(string $role): bool
    {
        foreach ($this->identities as $identity) foreach ($identity->findAll('role') as $claim) if ($claim->value === $role) return true;
        return false;
    }
    public function hasClaim(string $type, ?string $value = null): bool
    {
        foreach ($this->identities as $identity) foreach ($identity->findAll($type) as $claim) if ($value === null || $claim->value === $value) return true;
        return false;
    }
    public function findFirst(string $type): ?Claim
    {
        foreach ($this->identities as $identity) if ($claim = $identity->findFirst($type)) return $claim;
        return null;
    }
}
