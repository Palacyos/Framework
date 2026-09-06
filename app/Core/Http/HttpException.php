<?php
declare(strict_types=1);
namespace App\Core\Http;

class HttpException extends \RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        public readonly int $statusCode,
        string $message,
        public readonly array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
