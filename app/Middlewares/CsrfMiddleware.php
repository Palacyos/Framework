<?php

namespace App\Middlewares;

use App\Core\Csrf;

final class CsrfMiddleware
{
    public function handle(callable $next): void
    {
        Csrf::verify($_POST['_csrf'] ?? null);
        $next();
    }
}
