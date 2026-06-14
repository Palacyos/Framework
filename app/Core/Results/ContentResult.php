<?php

namespace App\Core\Results;

final class ContentResult implements IActionResult
{
    public function __construct(
        private string $content,
        private string $contentType = 'text/plain',
        private int    $status      = 200
    ) {}

    public function execute(): void
    {
        http_response_code($this->status);
        header("Content-Type: {$this->contentType}");
        echo $this->content;
    }
}
