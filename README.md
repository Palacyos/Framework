# Palacios Framework

Framework web MVC para PHP 8.5.

O Core é instalado pelo Composer em `vendor/palacios/framework`. A aplicação mantém apenas controllers, models, services, repositories, views e seu arquivo de bootstrap.

> Estado: pré-release. A primeira versão pública planejada é `v0.1.0`.

## Requisitos

- PHP 8.5
- Composer 2
- Extensões `json`, `mbstring`, `PDO` e `session`
- Um driver PDO compatível com o banco utilizado

## Criando uma aplicação

Após a publicação no Packagist:

```bash
composer global require palacios/framework:^0.1
framework new MinhaAplicacao
cd MinhaAplicacao
php -S localhost:8000 -t public
```

Durante o desenvolvimento deste repositório:

```bash
composer install
php bin/framework new MinhaAplicacao --no-install
```

O comando `new` copia o skeleton, cria `.env`, gera `APP_KEY`, remove a configuração local de desenvolvimento e, salvo com `--no-install`, executa o Composer.

## Estrutura da aplicação

```text
MinhaAplicacao/
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Repositories/
│   │   └── Interfaces/
│   ├── Services/
│   ├── Middleware/
│   └── Views/
├── bootstrap/
│   └── app.php
├── database/
│   └── migrations/
├── public/
│   └── index.php
├── resources/
├── tests/
├── .env
└── composer.json
```

`bootstrap/app.php` equivale, conceitualmente, ao `Program.cs`: registra serviços, monta o pipeline e mapeia endpoints. `public/index.php` é somente o ponto de entrada HTTP.

## Bootstrap

```php
<?php

use Palacios\Framework\App;
use Palacios\Framework\Hosting\ApplicationEnvironment;
use Palacios\Framework\Security\Authentication\AuthenticationOptions;
use Palacios\Framework\Security\Authorization\AuthorizationOptions;
use Palacios\Framework\Security\Authorization\PolicyBuilder;

$environment = $_ENV['APP_ENV'] ?? ApplicationEnvironment::PRODUCTION;
$builder = App::createBuilder(dirname(__DIR__), (string) $environment);

$builder->services()
    ->addControllers()
    ->addViews()
    ->addAuthentication(
        static fn (AuthenticationOptions $options) => $options
            ->loginPath('/login')
            ->accessDeniedPath('/acesso-negado'),
    )
    ->addAuthorization(
        static fn (AuthorizationOptions $options) => $options
            ->addPolicy('admin', static fn (PolicyBuilder $policy) => $policy
                ->requireAuthenticatedUser()
                ->requireRole('admin')),
    );

$app = $builder->build();

if ($builder->environment->isDevelopment()) {
    $app->useDevelopmentExceptionPage();
} else {
    $app->useExceptionHandler();
}

$app->useRequestLogging();
$app->useAntiforgery();
$app->useAuthentication();
$app->useAuthorization();
$app->mapControllers();

return $app;
```

A ordem de `use*()` define a ordem do pipeline.

## Injeção de dependência

```php
$builder->services()
    ->addSingleton(Cache::class, Cache::class)
    ->addScoped(IUserRepository::class, UserRepository::class)
    ->addTransient(Mailer::class, Mailer::class);
```

- `addSingleton`: uma instância durante a aplicação.
- `addScoped`: uma instância por requisição.
- `addTransient`: uma nova instância por resolução.

Controllers, handlers e seus parâmetros recebem dependências tipadas automaticamente.

## Controllers declarativos

```php
<?php

namespace App\Controllers;

use Palacios\Framework\Results\IActionResult;
use Palacios\Framework\Results\Results;
use Palacios\Framework\Routing\Attributes\HttpGet;
use Palacios\Framework\Routing\Attributes\Route;
use Palacios\Framework\Security\Attributes\Authorize;

#[Route('/users')]
#[Authorize]
final readonly class UsersController
{
    public function __construct(private IUserRepository $users) {}

    #[HttpGet('/{id:int}', name: 'users.show')]
    public function show(int $id): IActionResult
    {
        return Results::json($this->users->find($id));
    }
}
```

`addControllers()` descobre controllers em `app/Controllers`; `mapControllers()` registra as rotas encontradas.

## Binding e validação

Por convenção, valores tipados são obtidos da rota, query string, formulário, JSON ou container. Os atributos abaixo existem para remover ambiguidades:

- `#[FromRoute]`
- `#[FromQuery]`
- `#[FromForm]`
- `#[FromBody]`
- `#[FromHeader]`
- `#[FromServices]`

DTOs são opcionais. Interfaces, repositories e services podem ser utilizados diretamente. Quando um DTO é usado, as regras `Required`, `EmailAddress` e `StringLength` alimentam o `ModelState`.

O campo antiforgery não participa da hidratação de modelos tipados, portanto não precisa ser removido manualmente no controller.

## Minimal APIs e grupos de rotas

```php
$app->mapGet('/users/{id:int}', [UsersController::class, 'show'])
    ->withName('users.show')
    ->requireAuthorization('admin')
    ->withTags('Users')
    ->withSummary('Obtém um usuário')
    ->produces(200, 'application/json');

$url = $app->urlFor('users.show', ['id' => 42]);
```

Grupos compartilham prefixo, middleware e metadados e podem ser aninhados:

```php
$api = $app->mapGroup('/api')
    ->addMiddleware(ApiMiddleware::class)
    ->requireAuthorization()
    ->withTags('API');

$v1 = $api->mapGroup('/v1');
$v1->mapGet('/users', [UsersController::class, 'index']);
$v1->mapGet('/status', static fn () => Results::ok(['status' => 'ok']))
    ->allowAnonymous();
```

Rotas com o mesmo método e caminho, nomes duplicados e prefixos inválidos são rejeitados durante o registro.

## Autenticação e autorização

```php
$builder->services()
    ->addAuthentication()
    ->addAuthorization();

$app = $builder->build();
$app->useAuthentication();
$app->useAuthorization();

$app->mapGet('/private', $handler)->requireAuthorization();
$app->mapGet('/admin', $handler)->requireAuthorization('admin');
$app->mapGet('/public', $handler)->allowAnonymous();
```

Sem caminhos de interface configurados, falhas retornam `401` ou `403`. Com `loginPath()` e `accessDeniedPath()`, aplicações MVC recebem redirects.

## Banco de dados e views

```php
$builder->services()
    ->addViews(
        static fn (ViewOptions $options) => $options
            ->path('app/Views')
            ->defaultLayout('app'),
    )
    ->addDatabase(
        static fn (DatabaseOptions $options) => $options
            ->dsn($_ENV['DB_DSN'])
            ->username($_ENV['DB_USERNAME'])
            ->password($_ENV['DB_PASSWORD']),
    );
```

Repositories podem receber `PDO` pelo construtor. O Core não lê variáveis de ambiente diretamente durante o bootstrap web.

## CLI e geradores

```bash
framework make:controller Admin/Users
framework make:model User
framework make:service User
framework make:repository User
framework make:middleware RequestTrace
framework make:migration CreateUsers
```

Os geradores criam namespaces PSR-4 e nunca sobrescrevem arquivos existentes. `make:repository` cria a interface e sua implementação.

## Migrations

Uma migration retorna um objeto que implementa `Migration`:

```php
<?php

use Palacios\Framework\Database\Migration;

return new class implements Migration {
    public function up(PDO $connection): void
    {
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE users');
    }
};
```

Aplique migrations pendentes com:

```bash
framework database:update
```

A CLI usa `DB_DSN` ou monta a conexão com `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`. O histórico fica em `framework_migrations`.

## Resultados HTTP

Handlers podem retornar implementações de `IActionResult` usando `Results`:

```php
return Results::ok($value);
return Results::json($value);
return Results::created('/users/1', $value);
return Results::noContent();
return Results::badRequest($errors);
return Results::unauthorized();
return Results::forbid();
return Results::notFound();
return Results::redirect('/login');
return Results::view('users/index', ['users' => $users]);
```

Exceções são convertidas para `application/problem+json`. Detalhes internos aparecem somente com a página de desenvolvimento habilitada.

## Testes

```bash
composer test
composer analyse
composer test:smoke
composer check
```

```php
$factory = new WebApplicationFactory(
    static function (WebApplication $app): void {
        $app->mapGet('/ping', static fn () => Results::json(['status' => 'ok']));
    },
);

$response = $factory->createClient()->get('/ping');
```

O projeto usa PHPUnit 12, PHPStan no nível máximo e smoke tests sem servidor HTTP externo.

## Desenvolvimento do framework

```bash
composer install
composer check
composer test:smoke

cd template
composer install
composer test:smoke
```

- `src/`: Core distribuído pelo Composer.
- `resources/skeleton/`: aplicação copiada por `framework new`.
- `template/`: aplicação local de desenvolvimento, não incluída no pacote.
- `tests/`: testes do Core, não incluídos no pacote.

## Versionamento

Enquanto a API estiver em `0.x`, mudanças incompatíveis podem ocorrer em versões menores. A partir de `1.0.0`, o projeto seguirá Semantic Versioning.

Consulte [CHANGELOG.md](CHANGELOG.md), [SECURITY.md](SECURITY.md) e [LICENSE](LICENSE).
