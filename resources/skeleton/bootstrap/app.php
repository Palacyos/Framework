<?php

declare(strict_types=1);

use Palacios\Framework\App;
use Palacios\Framework\Hosting\ApplicationEnvironment;
use Palacios\Framework\Results\RedirectResult;
use Palacios\Framework\Security\Authentication\AuthenticationOptions;

$basePath = dirname(__DIR__);

$dotenv = Dotenv\Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

$environmentName = $_ENV['APP_ENV'] ?? ApplicationEnvironment::PRODUCTION;
$environmentName = is_string($environmentName) ? $environmentName : ApplicationEnvironment::PRODUCTION;

$builder = App::createBuilder($basePath, $environmentName);

$builder->services()
    ->addControllers()
    ->addViews()
    ->addAuthentication(
        static fn (AuthenticationOptions $options) => $options
            ->loginPath('/login')
            ->accessDeniedPath('/acesso-negado'),
    )
    ->addAuthorization();

$app = $builder->build();

if ($builder->environment->isDevelopment()) {
    $app->useDevelopmentExceptionPage();
} else {
    $app->useExceptionHandler();
}

// $app->useRequestLogging();
// $app->useAntiforgery();
// $app->useAuthentication();
// $app->useAuthorization();

$app->mapGet('/', static fn (): RedirectResult => new RedirectResult('/home'));
$app->mapControllers();

return $app;
