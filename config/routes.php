<?php

use App\Controllers\HomeController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

return [
    ['GET', '/', function () {
        header('Location: /home');
        exit();
    }],

    ['GET', '/home', [HomeController::class, 'index']],

    // ['GET',  '/dashboard', [DashboardController::class, 'index'],  [AuthMiddleware::class]],
    // ['GET',  '/admin',     [AdminController::class,    'index'],  [new AuthMiddleware(['admin'])]],
    // ['POST', '/exemplo',   [ExemploController::class,  'store'],  [CsrfMiddleware::class]],
];
