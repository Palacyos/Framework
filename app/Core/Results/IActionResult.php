<?php

namespace App\Core\Results;

interface IActionResult
{
    public function execute(): void;
}
