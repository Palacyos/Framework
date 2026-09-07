<?php
declare(strict_types=1);
namespace Palacios\Framework\Filters;
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class ServiceFilter { public function __construct(public string $type) {} }
