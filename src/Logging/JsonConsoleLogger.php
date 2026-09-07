<?php
declare(strict_types=1);
namespace Palacios\Framework\Logging;
final readonly class JsonConsoleLogger implements Logger
{
    public function __construct(private string $category = 'Application') {}
    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $record = ['timestamp' => gmdate(DATE_ATOM), 'level' => strtoupper($level), 'category' => $this->category, 'message' => $message, 'context' => $context];
        error_log(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void { $this->log('information', $message, $context); }
    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void { $this->log('error', $message, $context); }
}
