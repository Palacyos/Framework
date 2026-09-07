<?php
declare(strict_types=1);
namespace Palacios\Framework\Health;
final readonly class HealthCheckService
{
    /** @param array<string, HealthCheck|callable(): HealthCheckResult> $checks */
    public function __construct(private array $checks = []) {}
    /** @return array{status: string, checks: array<string, array<string, mixed>>} */
    public function check(): array
    {
        $entries = [];
        $overall = HealthStatus::Healthy;
        foreach ($this->checks as $name => $check) {
            $started = hrtime(true);
            try { $result = $check instanceof HealthCheck ? $check->check() : $check(); }
            catch (\Throwable $exception) { $result = HealthCheckResult::unhealthy($exception->getMessage()); }
            if ($result->status === HealthStatus::Unhealthy) $overall = HealthStatus::Unhealthy;
            elseif ($result->status === HealthStatus::Degraded && $overall === HealthStatus::Healthy) $overall = HealthStatus::Degraded;
            $entries[$name] = [...$result->jsonSerialize(), 'durationMs' => round((hrtime(true) - $started) / 1_000_000, 2)];
        }
        return ['status' => $overall->value, 'checks' => $entries];
    }
}
