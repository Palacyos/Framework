<?php

namespace App\Core\Results;

use App\Core\Http\HttpContext;

interface IActionResult
{
    public function execute(HttpContext $context): void;
}
