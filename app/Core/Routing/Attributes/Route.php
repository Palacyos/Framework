<?php
declare(strict_types=1);
namespace App\Core\Routing\Attributes;
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class Route { public function __construct(public string $path) {} }
