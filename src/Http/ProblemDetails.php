<?php
declare(strict_types=1);
namespace Palacios\Framework\Http;

final readonly class ProblemDetails implements \JsonSerializable
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        public int $status,
        public string $title,
        public ?string $detail = null,
        public string $type = 'about:blank',
        public ?string $traceId = null,
        public array $errors = [],
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return array_filter([
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'traceId' => $this->traceId,
            'errors' => $this->errors ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
