<?php

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

require BASE_PATH . '/app/Support/helpers.php';

use App\Controllers\HomeController;
use App\Core\App;
use App\Core\Results\RedirectResult;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

// ─── Builder ────────────────────────────────────────────────────────────────
$builder = App::createBuilder();

// Registre serviços com o lifetime adequado (como builder.Services.Add* no .NET)
// $builder->services()->addSingleton(IUserRepository::class, UserRepository::class);
// $builder->services()->addScoped(IUserRepository::class, UserRepository::class);
// $builder->services()->addTransient(IUserRepository::class, UserRepository::class);

$app = $builder->build();

// ─── Middleware global ───────────────────────────────────────────────────────
// $app->use(CsrfMiddleware::class);

// ─── Rotas ──────────────────────────────────────────────────────────────────
$app->mapGet('/', fn () => new RedirectResult('/home'));
$app->mapGet('/home', [HomeController::class, 'index']);

// Exemplos:
// $app->mapGet('/users/{id}',    [UserController::class, 'show'],   [AuthMiddleware::class]);
// $app->mapPost('/users',        [UserController::class, 'store'],  [CsrfMiddleware::class]);
// $app->mapPut('/users/{id}',    [UserController::class, 'update'], [CsrfMiddleware::class, AuthMiddleware::class]);
// $app->mapDelete('/users/{id}', [UserController::class, 'destroy'],[AuthMiddleware::class]);

// ─── Run ─────────────────────────────────────────────────────────────────────
$app->run();
