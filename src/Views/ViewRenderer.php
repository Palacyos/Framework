<?php

declare(strict_types=1);

namespace Palacios\Framework\Views;

use RuntimeException;

final readonly class ViewRenderer
{
    public function __construct(private ViewOptions $options) {}

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        extract($data, EXTR_SKIP);

        $viewFile = $this->options->getPath() . '/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException("View não encontrada: {$viewFile}");
        }

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        $layout ??= $this->options->getDefaultLayout();
        $layoutFile = $this->options->getPath() . '/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new RuntimeException("Layout não encontrado: {$layoutFile}");
        }

        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }
}
