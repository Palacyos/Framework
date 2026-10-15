<?php

declare(strict_types=1);

namespace Palacios\Framework\Views;

use RuntimeException;
use Throwable;

final readonly class ViewRenderer
{
    public function __construct(private ViewOptions $options) {}

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $initialBufferLevel = ob_get_level();
        extract($data, EXTR_SKIP);

        $viewFile = $this->options->getPath() . '/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException("View não encontrada: {$viewFile}");
        }

        try {
            ob_start();
            require $viewFile;
            $content = $this->collectOutput($initialBufferLevel);

            $layout ??= $this->options->getDefaultLayout();
            $layoutFile = $this->options->getPath() . '/layouts/' . $layout . '.php';
            if (!is_file($layoutFile)) {
                throw new RuntimeException("Layout não encontrado: {$layoutFile}");
            }

            ob_start();
            require $layoutFile;
            return $this->collectOutput($initialBufferLevel);
        } catch (Throwable $exception) {
            $this->discardOutput($initialBufferLevel);
            throw $exception;
        }
    }

    private function collectOutput(int $initialBufferLevel): string
    {
        // Incorpora os buffers do template ao buffer do renderizador, em ordem.
        while (ob_get_level() > $initialBufferLevel + 1) {
            if (!$this->isCurrentBufferRemovable()) {
                throw new RuntimeException('O template criou um buffer de saída não removível.');
            }
            if (!ob_end_flush()) {
                throw new RuntimeException('Não foi possível finalizar o buffer de saída do template.');
            }
        }

        if (ob_get_level() !== $initialBufferLevel + 1) {
            throw new RuntimeException('O template fechou o buffer de saída do renderizador.');
        }

        $output = ob_get_clean();
        if ($output === false) {
            throw new RuntimeException('Não foi possível capturar a saída do template.');
        }

        return $output;
    }

    private function discardOutput(int $initialBufferLevel): void
    {
        while (ob_get_level() > $initialBufferLevel) {
            $level = ob_get_level();
            if (!$this->isCurrentBufferRemovable()) {
                // PHP não permite remover esse buffer nem alcançar os inferiores.
                break;
            }

            try {
                ob_end_clean();
            } catch (Throwable) { // @phpstan-ignore catch.neverThrown (ob_end_clean executa callbacks que podem lançar exceções)
                // Um callback de limpeza não deve substituir a exceção original.
            }

            if (ob_get_level() >= $level) {
                break;
            }
        }
    }

    private function isCurrentBufferRemovable(): bool
    {
        $flags = ob_get_status()['flags'] ?? 0;
        return is_int($flags) && ($flags & PHP_OUTPUT_HANDLER_REMOVABLE) !== 0;
    }
}
