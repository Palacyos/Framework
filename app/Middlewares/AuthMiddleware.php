<?php

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Results\RedirectResult;

final class AuthMiddleware
{
    public function __construct(
        private array $allowedTypes = []
    ) {}

    public function handle(callable $next): void
    {
        if (!Auth::check()) {
            (new RedirectResult('/login'))->execute();
        }

        if (!empty($this->allowedTypes) && !in_array(Auth::type(), $this->allowedTypes)) {
            (new RedirectResult('/?error=access_denied'))->execute();
        }

        $next();
    }
}
