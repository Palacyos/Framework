<?php
declare(strict_types=1);
namespace Palacios\Framework\Testing;

use Palacios\Framework\App;
use Palacios\Framework\WebApplication;
use Palacios\Framework\WebApplicationBuilder;

class WebApplicationFactory
{
    public function __construct(private readonly ?\Closure $configure = null) {}

    public function createApplication(): WebApplication
    {
        $builder = App::createBuilder();
        $this->configureServices($builder);
        $application = $builder->build();
        $this->configureApplication($application);
        if ($this->configure !== null) ($this->configure)($application, $builder);
        return $application;
    }

    public function createClient(): TestClient { return new TestClient($this->createApplication()); }
    protected function configureServices(WebApplicationBuilder $builder): void {}
    protected function configureApplication(WebApplication $application): void {}
}
