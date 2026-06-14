<?php

namespace App\Controllers;

use App\Core\Results\IActionResult;
use App\Core\Results\ViewResult;

final class HomeController
{
    public function index(): IActionResult
    {
        return new ViewResult('home');
    }
}
