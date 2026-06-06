<?php

namespace App\Middlewares;

use App\Core\Auth;

final class AuthMiddleware
{
    public function __construct(
        private array $allowedTypes = []
    ) {}

    public function handle(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit();
        }

        if (!empty($this->allowedTypes) && !in_array(Auth::type(), $this->allowedTypes)) {
            header('Location: /?error=access_denied');
            exit();
        }
    }
}
