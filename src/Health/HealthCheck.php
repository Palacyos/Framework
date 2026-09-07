<?php
declare(strict_types=1);
namespace Palacios\Framework\Health;
interface HealthCheck { public function check(): HealthCheckResult; }
