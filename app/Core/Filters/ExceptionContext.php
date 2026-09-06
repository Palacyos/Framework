<?php
declare(strict_types=1);
namespace App\Core\Filters;
use App\Core\Http\HttpContext;
use App\Core\Results\IActionResult;
final class ExceptionContext
{
    public ?IActionResult $result = null;
    public bool $handled = false;
    public function __construct(public readonly HttpContext $httpContext, public readonly \Throwable $exception) {}
}
