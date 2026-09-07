<?php
declare(strict_types=1);
namespace Palacios\Framework\Security\Attributes;
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class AllowAnonymous {}
