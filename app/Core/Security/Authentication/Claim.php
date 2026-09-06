<?php
declare(strict_types=1);
namespace App\Core\Security\Authentication;
final readonly class Claim
{
    public function __construct(public string $type, public string $value) {}
}
