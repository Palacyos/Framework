<?php
declare(strict_types=1);
namespace Palacios\Framework\Http;

final class HttpResponse
{
    private int $statusCode = 200;
    /** @var array<string, string> */
    private array $headers = [];
    private string $body = '';
    private bool $started = false;
    private bool $completed = false;

    /** @var list<callable(): void> */
    private array $completionCallbacks = [];

    public function status(int $code): self
    {
        if ($code < 100 || $code > 599) throw new \InvalidArgumentException("Status HTTP inválido: {$code}");
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        if (preg_match('/[\r\n]/', $name . $value)) throw new \InvalidArgumentException('Header HTTP inválido.');
        $this->headers[$name] = $value;
        return $this;
    }

    public function write(string $content): self { $this->body .= $content; return $this; }

    /** @param callable(): void $callback */
    public function onCompleted(callable $callback): self
    {
        if ($this->completed) throw new \LogicException('A resposta já foi concluída.');
        $this->completionCallbacks[] = $callback;
        return $this;
    }

    public function complete(): void
    {
        if ($this->completed) return;
        $this->completed = true;
        $callbacks = array_reverse($this->completionCallbacks);
        $this->completionCallbacks = [];

        $failure = null;
        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable $exception) {
                $failure ??= $exception;
            }
        }

        if ($failure !== null) throw $failure;
    }
    public function send(): void { if ($this->started) return; $this->started = true; http_response_code($this->statusCode); foreach ($this->headers as $name => $value) header("{$name}: {$value}"); echo $this->body; }
    public function statusCode(): int { return $this->statusCode; }
    /** @return array<string, string> */
    public function headers(): array { return $this->headers; }
    public function body(): string { return $this->body; }
}
