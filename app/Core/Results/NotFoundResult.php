<?php

namespace App\Core\Results;

final class NotFoundResult implements IActionResult
{
    public function __construct(private string $message = '404 Not Found') {}

    public function execute(): void
    {
        http_response_code(404);
        echo $this->message;
    }
}
