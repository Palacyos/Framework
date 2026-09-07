<?php

declare(strict_types=1);

namespace Palacios\Framework\Security\Authentication;

final class AuthenticationOptions
{
    private ?string $loginPath = null;
    private ?string $accessDeniedPath = null;

    public function loginPath(string $path): self
    {
        $this->loginPath = $path;
        return $this;
    }

    public function accessDeniedPath(string $path): self
    {
        $this->accessDeniedPath = $path;
        return $this;
    }

    public function getLoginPath(): ?string
    {
        return $this->loginPath;
    }

    public function getAccessDeniedPath(): ?string
    {
        return $this->accessDeniedPath;
    }
}
