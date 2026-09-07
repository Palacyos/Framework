<?php
declare(strict_types=1);
namespace Palacios\Framework\Filters;
use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Results\IActionResult;
final readonly class ResultExecutingContext
{
    public function __construct(public HttpContext $httpContext, public IActionResult $result) {}
}
