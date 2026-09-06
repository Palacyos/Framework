<?php
declare(strict_types=1);
namespace App\Core\Health;
interface HealthCheck { public function check(): HealthCheckResult; }
