<?php
declare(strict_types=1);
namespace App\Core\Security\Attributes;
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class Authorize
{
    /** @param list<string> $roles */
    public function __construct(public ?string $policy = null, public array $roles = []) {}
}
