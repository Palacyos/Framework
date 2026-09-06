<?php
declare(strict_types=1);
namespace App\Core\Filters;
use App\Core\Http\HttpContext;
use App\Core\Results\IActionResult;
final class ActionExecutingContext
{
    public ?IActionResult $result = null;
    /** @param list<mixed> $arguments */
    public function __construct(public readonly HttpContext $httpContext, public readonly array $arguments) {}
}
