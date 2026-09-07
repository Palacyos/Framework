<?php

declare(strict_types=1);

namespace Palacios\Framework\Hosting;

final readonly class ApplicationEnvironment
{
    public const DEVELOPMENT = 'Development';
    public const TESTING = 'Testing';
    public const PRODUCTION = 'Production';

    public string $name;

    public function __construct(string $name = self::PRODUCTION)
    {
        $normalized = strtolower(trim($name));
        $this->name = match ($normalized) {
            'development', 'dev', 'local' => self::DEVELOPMENT,
            'testing', 'test' => self::TESTING,
            default => self::PRODUCTION,
        };
    }

    public function isDevelopment(): bool { return $this->name === self::DEVELOPMENT; }
    public function isTesting(): bool { return $this->name === self::TESTING; }
    public function isProduction(): bool { return $this->name === self::PRODUCTION; }
}
