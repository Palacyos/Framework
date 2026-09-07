<?php

namespace Palacios\Framework;

use Palacios\Framework\Hosting\ApplicationEnvironment;
use Palacios\Framework\Routing\ControllerDiscovery;

final class WebApplicationBuilder
{
    private ServiceCollection $services;

    public function __construct(
        private readonly string $basePath = '',
        public readonly ApplicationEnvironment $environment = new ApplicationEnvironment(),
    ) {
        $this->services = new ServiceCollection($this->basePath);
    }

    public function services(): ServiceCollection
    {
        return $this->services;
    }

    public function build(): WebApplication
    {
        $container = new Container($this->services->getBindings());

        $controllers = (new ControllerDiscovery($this->basePath))
            ->discover($this->services->getControllerSources());

        return new WebApplication($container, $controllers, $this->environment);
    }
}
