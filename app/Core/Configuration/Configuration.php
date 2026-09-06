<?php
declare(strict_types=1);
namespace App\Core\Configuration;

final readonly class Configuration
{
    /** @param array<array-key, mixed> $values */
    public function __construct(private array $values = []) {}

    /** @param array<array-key, mixed> $environment */
    public static function fromEnvironment(array $environment): self
    {
        $values = [];
        foreach ($environment as $key => $value) {
            if (!is_string($key)) continue;
            $segments = explode('__', strtolower($key));
            $leaf = array_pop($segments);
            $nested = [$leaf => $value];
            foreach (array_reverse($segments) as $segment) {
                $nested = [$segment => $nested];
            }
            $values = array_replace_recursive($values, $nested);
        }
        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', strtolower($key)) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) return $default;
            $value = $value[$segment];
        }
        return $value;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);
        if ($value === null) return null;
        if (!is_scalar($value) && !$value instanceof \Stringable) return $default;
        return (string) $value;
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->get($key, $default);
        return filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
