<?php
declare(strict_types=1);
namespace App\Core\Health;
enum HealthStatus: string { case Healthy = 'Healthy'; case Degraded = 'Degraded'; case Unhealthy = 'Unhealthy'; }
