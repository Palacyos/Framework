<?php
declare(strict_types=1);
namespace App\Core\Binding\Attributes;
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class FromRoute { public function __construct(public ?string $name = null) {} }
