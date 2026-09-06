<?php
declare(strict_types=1);
namespace App\Core\Testing;

use App\Core\App;
use App\Core\WebApplication;
use App\Core\WebApplicationBuilder;

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
