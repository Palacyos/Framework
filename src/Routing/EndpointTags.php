<?php
declare(strict_types=1);
namespace Palacios\Framework\Routing;
final readonly class EndpointTags
{
    /** @param list<string> $tags */
    public function __construct(public array $tags) {}
}
