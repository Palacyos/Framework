<?php
declare(strict_types=1);
namespace Palacios\Framework\Health;
enum HealthStatus: string { case Healthy = 'Healthy'; case Degraded = 'Degraded'; case Unhealthy = 'Unhealthy'; }
