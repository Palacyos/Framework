<?php

namespace Palacios\Framework\Results;

use Palacios\Framework\Http\HttpContext;

final readonly class RedirectResult implements IActionResult
{
    public function __construct(
        private string $url,
        private int    $status = 302
    ) {}

    public function execute(HttpContext $context): void
    {
        $context->response->status($this->status)->header('Location', $this->url);
    }
}
