<?php

namespace App\Core\Results;

use JetBrains\PhpStorm\NoReturn;

final readonly class RedirectResult implements IActionResult
{
    public function __construct(
        private string $url,
        private int    $status = 302
    ) {}

    #[NoReturn]
    public function execute(): void
    {
        http_response_code($this->status);
        header("Location: {$this->url}");
        exit();
    }
}
