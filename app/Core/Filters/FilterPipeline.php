<?php
declare(strict_types=1);
namespace App\Core\Filters;

use App\Core\Container;
use App\Core\Http\HttpContext;
use App\Core\Results\IActionResult;

final readonly class FilterPipeline
{
    public function __construct(private Container $services) {}

    /** @param list<mixed> $arguments */
    public function invokeAction(HttpContext $context, array $arguments, callable $action): mixed
    {
        $filters = $this->resolve($context, IActionFilter::class);
        $executing = new ActionExecutingContext($context, $arguments);
        foreach ($filters as $filter) {
            $filter->onActionExecuting($executing);
            if ($executing->result !== null) return $executing->result;
        }

        try {
            $result = $action();
            $executed = new ActionExecutedContext($context, $result);
        } catch (\Throwable $exception) {
            $executed = new ActionExecutedContext($context, null, $exception);
            foreach (array_reverse($filters) as $filter) $filter->onActionExecuted($executed);
            throw $exception;
        }

        foreach (array_reverse($filters) as $filter) $filter->onActionExecuted($executed);
        return $result;
    }

    public function invokeResult(HttpContext $context, IActionResult $result): void
    {
        $filters = $this->resolve($context, IResultFilter::class);
        $filterContext = new ResultExecutingContext($context, $result);
        foreach ($filters as $filter) $filter->onResultExecuting($filterContext);
        $result->execute($context);
        foreach (array_reverse($filters) as $filter) $filter->onResultExecuted($filterContext);
    }

    public function handleException(HttpContext $context, \Throwable $exception): ?IActionResult
    {
        $filterContext = new ExceptionContext($context, $exception);
        foreach (array_reverse($this->resolve($context, IExceptionFilter::class)) as $filter) $filter->onException($filterContext);
        return $filterContext->handled ? $filterContext->result : null;
    }

    /**
     * @template T of object
     * @param class-string<T> $contract
     * @return list<T>
     */
    private function resolve(HttpContext $context, string $contract): array
    {
        $metadata = $context->endpoint['metadata'] ?? null;
        if ($metadata === null) return [];
        $filters = [];
        foreach ($metadata->all() as $item) {
            $filter = $item instanceof ServiceFilter ? $this->services->make($item->type) : $item;
            if ($filter instanceof $contract) $filters[] = $filter;
        }
        return $filters;
    }
}
