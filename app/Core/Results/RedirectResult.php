<?php

namespace App\Core\Results;

final class RedirectResult implements IActionResult
{
    public function __construct(
        private string $url,
        private int    $status = 302
    ) {}

    public function execute(): void
    {
        http_response_code($this->status);
        header("Location: {$this->url}");
        exit();
    }
}
