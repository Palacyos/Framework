<?php
declare(strict_types=1);
namespace App\Core\Filters;
use App\Core\Http\HttpContext;
final readonly class ActionExecutedContext
{
    public function __construct(public HttpContext $httpContext, public mixed $result, public ?\Throwable $exception = null) {}
}
