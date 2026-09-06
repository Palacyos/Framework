<?php
declare(strict_types=1);
namespace App\Core\Filters;
interface IActionFilter
{
    public function onActionExecuting(ActionExecutingContext $context): void;
    public function onActionExecuted(ActionExecutedContext $context): void;
}
