<?php
declare(strict_types=1);
namespace Palacios\Framework\Health;
final readonly class HealthCheckResult implements \JsonSerializable
{
    /** @param array<string, mixed> $data */
    public function __construct(public HealthStatus $status, public string $description = '', public array $data = []) {}
    /** @param array<string, mixed> $data */
    public static function healthy(string $description = '', array $data = []): self { return new self(HealthStatus::Healthy, $description, $data); }
    /** @param array<string, mixed> $data */
    public static function unhealthy(string $description = '', array $data = []): self { return new self(HealthStatus::Unhealthy, $description, $data); }
    /** @return array{status: string, description: string, data: array<string, mixed>} */
    public function jsonSerialize(): array { return ['status' => $this->status->value, 'description' => $this->description, 'data' => $this->data]; }
}
