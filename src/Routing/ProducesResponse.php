<?php
declare(strict_types=1);
namespace Palacios\Framework\Routing;
final readonly class ProducesResponse
{
    public function __construct(public int $statusCode, public ?string $contentType = null) {}
}
