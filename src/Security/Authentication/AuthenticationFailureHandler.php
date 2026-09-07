<?php

declare(strict_types=1);

namespace Palacios\Framework\Security\Authentication;

use Palacios\Framework\Http\HttpContext;

interface AuthenticationFailureHandler
{
    public function challenge(HttpContext $context): void;

    public function forbid(HttpContext $context): void;
}
