<?php

namespace App\Controllers;

use Palacios\Framework\Results\IActionResult;
use Palacios\Framework\Results\ViewResult;
use Palacios\Framework\Routing\Attributes\HttpGet;
use Palacios\Framework\Routing\Attributes\Route;

#[Route('/home')]
final class HomeController
{
    #[HttpGet(name: 'home')]
    public function index(): IActionResult
    {
        return new ViewResult('home');
    }
}
