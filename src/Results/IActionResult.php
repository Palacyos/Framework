<?php

namespace Palacios\Framework\Results;

use Palacios\Framework\Http\HttpContext;

interface IActionResult
{
    public function execute(HttpContext $context): void;
}
