<?php

namespace App\Core;

use RuntimeException;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        echo self::renderToString($view, $data, $layout);
    }

    /** @param array<string, mixed> $data */
    public static function renderToString(string $view, array $data = [], string $layout = 'app'): string
    {
        extract($data, EXTR_SKIP);

        $viewFile = __DIR__ . '/../Views/' . $view . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException("View não encontrada: {$viewFile}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = __DIR__ . '/../Views/layouts/' . $layout . '.php';

        if (!is_file($layoutFile)) {
            throw new RuntimeException("Layout não encontrado: {$layoutFile}");
        }

        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }
}
