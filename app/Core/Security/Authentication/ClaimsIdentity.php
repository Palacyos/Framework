<?php
declare(strict_types=1);
namespace App\Core\Security\Authentication;
final readonly class ClaimsIdentity
{
    /** @param list<Claim> $claims */
    public function __construct(private array $claims = [], public ?string $authenticationType = null) {}
    public function isAuthenticated(): bool { return $this->authenticationType !== null; }
    /** @return list<Claim> */
    public function claims(): array { return $this->claims; }
    public function findFirst(string $type): ?Claim
    {
        foreach ($this->claims as $claim) if ($claim->type === $type) return $claim;
        return null;
    }
    /** @return list<Claim> */
    public function findAll(string $type): array { return array_values(array_filter($this->claims, static fn (Claim $claim): bool => $claim->type === $type)); }
}
