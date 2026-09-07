<?php

namespace Palacios\Framework;

use Palacios\Framework\Hosting\ApplicationEnvironment;

final class App
{
    public static function createBuilder(
        ?string $basePath = null,
        string $environmentName = ApplicationEnvironment::PRODUCTION,
    ): WebApplicationBuilder
    {
        return new WebApplicationBuilder(
            $basePath ?? (getcwd() ?: '.'),
            new ApplicationEnvironment($environmentName),
        );
    }
}
