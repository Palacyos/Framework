<?php

namespace App\Core\Results;

final class JsonResult implements IActionResult
{
    public function __construct(
        private mixed $data,
        private int   $status = 200
    ) {}

    public function execute(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json');
        echo json_encode($this->data);
    }
}
