<?php
declare(strict_types=1);
namespace App\Middlewares;

use App\Core\Http\HttpContext;
use App\Core\Security\Authentication\SessionAuthentication;

final readonly class AuthenticationMiddleware
{
    public function __construct(private SessionAuthentication $authentication = new SessionAuthentication()) {}
    public function handle(HttpContext $context, callable $next): void
    {
        $context->user = $this->authentication->authenticate();
        $next();
    }
}
