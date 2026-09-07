<?php
declare(strict_types=1);
namespace Palacios\Framework\Filters;
use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Results\IActionResult;
final class ActionExecutingContext
{
    public ?IActionResult $result = null;
    /** @param list<mixed> $arguments */
    public function __construct(public readonly HttpContext $httpContext, public readonly array $arguments) {}
}
