<?php

namespace App\Middlewares;

use App\Core\Csrf;
use App\Core\Http\HttpContext;

final class CsrfMiddleware
{
    public function handle(HttpContext $context, callable $next): void
    {
        if ($context->request->isSafeMethod()) {
            $next();
            return;
        }
        $submitted = $context->request->form['_csrf'] ?? $context->request->header('x-csrf-token');
        Csrf::verify(is_string($submitted) ? $submitted : null);
        $next();
    }
}
