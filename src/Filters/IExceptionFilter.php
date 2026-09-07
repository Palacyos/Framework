<?php
declare(strict_types=1);
namespace Palacios\Framework\Filters;
interface IExceptionFilter { public function onException(ExceptionContext $context): void; }
