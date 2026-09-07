<?php
declare(strict_types=1);
namespace Palacios\Framework\Security\Authentication;
final readonly class Claim
{
    public function __construct(public string $type, public string $value) {}
}
