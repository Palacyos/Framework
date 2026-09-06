<?php
declare(strict_types=1);
namespace App\Core\Http;

final readonly class HttpRequest
{
    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $form
     * @param array<array-key, mixed> $cookies
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $server
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $form = [],
        public array $cookies = [],
        public array $files = [],
        public array $server = [],
        public array $headers = [],
        public string $body = '',
    ) {}

    public static function capture(): self
    {
        $server = $_SERVER;
        $uri = $server['REQUEST_URI'] ?? '/';
        $uri = is_string($uri) ? $uri : '/';
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '/'));
        $headers = [];
        foreach ($server as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_') && is_scalar($value)) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        $method = $server['REQUEST_METHOD'] ?? 'GET';
        $body = file_get_contents('php://input');
        return new self(strtoupper(is_string($method) ? $method : 'GET'), $path, $_GET, $_POST, $_COOKIE, $_FILES, $server, $headers, $body === false ? '' : $body);
    }

    public function header(string $name, ?string $default = null): ?string { return $this->headers[strtolower($name)] ?? $default; }
    /** @return array<array-key, mixed> */
    public function json(): array
    {
        if ($this->body === '') return [];
        $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new \JsonException('O corpo JSON deve ser um objeto ou lista.');
        return $decoded;
    }
    public function isSafeMethod(): bool { return in_array($this->method, ['GET', 'HEAD', 'OPTIONS', 'TRACE'], true); }
}
