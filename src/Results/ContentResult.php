<?php

namespace Palacios\Framework\Results;

use Palacios\Framework\Http\HttpContext;

final readonly class ContentResult implements IActionResult
{
    public function __construct(
        private string $content,
        private string $contentType = 'text/plain',
        private int    $status      = 200
    ) {}

    public function execute(HttpContext $context): void
    {
        $context->response
            ->status($this->status)
            ->header('Content-Type', $this->contentType . '; charset=utf-8')
            ->write($this->content);
    }
}
