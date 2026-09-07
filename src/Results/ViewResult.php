<?php

declare(strict_types=1);

namespace Palacios\Framework\Results;

use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Views\ViewRenderer;

final readonly class ViewResult implements IActionResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private string $view,
        private array $data = [],
        private ?string $layout = null,
    ) {}

    public function execute(HttpContext $context): void
    {
        $renderer = $context->services->make(ViewRenderer::class);
        if (!$renderer instanceof ViewRenderer) {
            throw new \RuntimeException('O serviço de views não foi registrado.');
        }

        $context->response
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->write($renderer->render($this->view, $this->data, $this->layout));
    }
}
