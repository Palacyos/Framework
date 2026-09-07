<?php

namespace Palacios\Framework\Results;

use Palacios\Framework\Http\HttpContext;

final readonly class NotFoundResult implements IActionResult
{
    public function __construct(private string $message = '404 Not Found') {}

    public function execute(HttpContext $context): void
    {
        $context->response->status(404)->header('Content-Type', 'text/plain; charset=utf-8')->write($this->message);
    }
}
