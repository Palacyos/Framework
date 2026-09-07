<?php
declare(strict_types=1);
namespace Palacios\Framework\Routing;
final readonly class EndpointSummary
{
    public function __construct(public string $summary) {}
}
