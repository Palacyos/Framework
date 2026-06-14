<?php

namespace App\Core\Results;

use App\Core\View;

final readonly class ViewResult implements IActionResult
{
    public function __construct(
        private string $view,
        private array  $data   = [],
        private string $layout = 'app'
    ) {}

    public function execute(): void
    {
        View::render($this->view, $this->data, $this->layout);
    }
}
