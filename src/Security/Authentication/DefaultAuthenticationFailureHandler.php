<?php

declare(strict_types=1);

namespace Palacios\Framework\Security\Authentication;

use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Http\HttpException;
use Palacios\Framework\Results\RedirectResult;

final readonly class DefaultAuthenticationFailureHandler implements AuthenticationFailureHandler
{
    public function __construct(
        private AuthenticationOptions $options = new AuthenticationOptions(),
    ) {}

    public function challenge(HttpContext $context): void
    {
        $path = $this->options->getLoginPath();
        if ($path === null) {
            throw new HttpException(401, 'Autenticação necessária.');
        }

        (new RedirectResult($path))->execute($context);
    }

    public function forbid(HttpContext $context): void
    {
        $path = $this->options->getAccessDeniedPath();
        if ($path === null) {
            throw new HttpException(403, 'Acesso negado.');
        }

        (new RedirectResult($path))->execute($context);
    }
}
