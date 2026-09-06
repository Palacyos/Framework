<?php
declare(strict_types=1);
namespace App\Core\Filters;
use App\Core\Http\HttpContext;
use App\Core\Results\IActionResult;
final readonly class ResultExecutingContext
{
    public function __construct(public HttpContext $httpContext, public IActionResult $result) {}
}
