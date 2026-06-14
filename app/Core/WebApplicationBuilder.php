<?php

namespace App\Core;

final class WebApplicationBuilder
{
    private ServiceCollection $services;

    public function __construct()
    {
        $this->services = new ServiceCollection();
    }

    public function services(): ServiceCollection
    {
        return $this->services;
    }

    public function build(): WebApplication
    {
        $container = new Container($this->services->getBindings());
        return new WebApplication($container);
    }
}
