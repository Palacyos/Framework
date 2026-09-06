<?php

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Http\HttpContext;
use App\Core\Results\RedirectResult;

final readonly class AuthMiddleware
{
    /** @param list<string> $allowedTypes */
    public function __construct(
        private array $allowedTypes = []
    ) {}

    public function handle(HttpContext $context, callable $next): void
    {
        if (!Auth::check()) {
            (new RedirectResult('/login'))->execute($context);
            return;
        }

        if (!empty($this->allowedTypes) && !in_array(Auth::type(), $this->allowedTypes)) {
            (new RedirectResult('/?error=access_denied'))->execute($context);
            return;
        }

        $next();
    }
}
