<?php
declare(strict_types=1);
namespace Palacios\Framework\Filters;
interface IResultFilter
{
    public function onResultExecuting(ResultExecutingContext $context): void;
    public function onResultExecuted(ResultExecutingContext $context): void;
}
