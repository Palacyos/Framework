<?php
declare(strict_types=1);
namespace Palacios\Framework\Filters;
interface IActionFilter
{
    public function onActionExecuting(ActionExecutingContext $context): void;
    public function onActionExecuted(ActionExecutedContext $context): void;
}
