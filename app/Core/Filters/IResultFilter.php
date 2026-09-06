<?php
declare(strict_types=1);
namespace App\Core\Filters;
interface IResultFilter
{
    public function onResultExecuting(ResultExecutingContext $context): void;
    public function onResultExecuted(ResultExecutingContext $context): void;
}
