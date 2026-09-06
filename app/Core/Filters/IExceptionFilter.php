<?php
declare(strict_types=1);
namespace App\Core\Filters;
interface IExceptionFilter { public function onException(ExceptionContext $context): void; }
