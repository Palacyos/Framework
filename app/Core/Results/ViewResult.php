<?php

namespace App\Core\Results;

use App\Core\View;
use App\Core\Http\HttpContext;

final readonly class ViewResult implements IActionResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private string $view,
        private array  $data   = [],
        private string $layout = 'app'
    ) {}

    public function execute(HttpContext $context): void
    {
        $context->response
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->write(View::renderToString($this->view, $this->data, $this->layout));
    }
}
