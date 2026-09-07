<?php

declare(strict_types=1);

namespace Palacios\Framework\Views;

final class ViewOptions
{
    private string $path = 'app/Views';
    private string $defaultLayout = 'app';

    public function path(string $path): self
    {
        $this->path = rtrim($path, '/\\');
        return $this;
    }

    public function defaultLayout(string $layout): self
    {
        $this->defaultLayout = $layout;
        return $this;
    }

    public function getPath(): string { return $this->path; }
    public function getDefaultLayout(): string { return $this->defaultLayout; }
}
