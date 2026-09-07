<?php
declare(strict_types=1);
namespace Palacios\Framework\Filters;
use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Results\IActionResult;
final class ExceptionContext
{
    public ?IActionResult $result = null;
    public bool $handled = false;
    public function __construct(public readonly HttpContext $httpContext, public readonly \Throwable $exception) {}
}
