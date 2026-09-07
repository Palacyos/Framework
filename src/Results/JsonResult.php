<?php

namespace Palacios\Framework\Results;

use Palacios\Framework\Http\HttpContext;

final readonly class JsonResult implements IActionResult
{
    /** @param array<string, string> $headers */
    public function __construct(
        private mixed $data,
        private int   $status = 200,
        private array $headers = [],
    ) {}

    public function execute(HttpContext $context): void
    {
        $context->response
            ->status($this->status)
            ->header('Content-Type', 'application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) $context->response->header($name, $value);
        $context->response->write(json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
