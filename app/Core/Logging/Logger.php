<?php
declare(strict_types=1);
namespace App\Core\Logging;
interface Logger
{
    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void;
    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void;
    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void;
}
