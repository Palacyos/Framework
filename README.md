# Palacios Framework

Framework web MVC moderno para PHP 8.5, com roteamento declarativo, injeção de dependência, model binding, validação, middleware, autenticação, autorização, proteção antiforgery, views, banco de dados, migrations e ferramentas de linha de comando.

O código da aplicação fica separado do framework: o Composer instala o núcleo em `vendor/palacios/framework`, enquanto seu projeto mantém apenas as regras de negócio, controllers, models, repositories, services e views.

## Requisitos

Antes de começar, instale:

- PHP 8.5 ou superior;
- Composer 2;
- extensões PHP `json`, `mbstring`, `PDO` e `session`;
- um driver PDO para o banco escolhido, como `pdo_mysql` ou `pdo_sqlite`.

Confirme o ambiente:

```bash
php --version
composer --version
```

## Criando seu primeiro projeto

Instale a ferramenta globalmente:

```bash
composer global require palacios/framework:^0.1
```

Crie e execute uma aplicação:

```bash
framework new MinhaAplicacao
cd MinhaAplicacao
php -S localhost:8000 -t public
```

Acesse [http://localhost:8000](http://localhost:8000).

Se o comando `framework` não for encontrado, adicione o diretório global de binários do Composer ao `PATH` ou execute:

```bash
~/.config/composer/vendor/bin/framework new MinhaAplicacao
```

O comando `new`:

- copia a estrutura inicial da aplicação;
- cria o arquivo `.env`;
- gera automaticamente uma `APP_KEY`;
- configura a dependência da versão instalada;
- executa `composer install`.

Use `--no-install` quando quiser instalar as dependências depois:

```bash
framework new MinhaAplicacao --no-install
```

## Estrutura da aplicação

```text
MinhaAplicacao/
├── app/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Models/
│   ├── Repositories/
│   │   └── Interfaces/
│   ├── Services/
│   ├── Support/
│   └── Views/
│       └── layouts/
├── bootstrap/
│   └── app.php
├── database/
│   └── migrations/
├── public/
│   ├── assets/
│   └── index.php
├── resources/
├── tests/
├── .env
└── composer.json
```

Principais diretórios:

- `app/`: código da sua aplicação;
- `bootstrap/app.php`: registro de serviços, middleware e rotas;
- `database/migrations/`: alterações versionadas do banco;
- `public/`: único diretório que deve ficar exposto pelo servidor web;
- `resources/`: arquivos de estilo e outros recursos;
- `tests/`: testes da aplicação;
- `vendor/`: dependências gerenciadas pelo Composer; não edite esse diretório.

## Como uma requisição é processada

O arquivo `public/index.php` inicia a aplicação definida em `bootstrap/app.php`. O pipeline executa os middleware habilitados, encontra a rota correspondente, resolve as dependências do controller, converte os dados da requisição em parâmetros tipados e produz a resposta HTTP.

A ordem das chamadas `use*()` em `bootstrap/app.php` também é a ordem do pipeline.

## Configuração inicial

O projeto gerado carrega as variáveis do arquivo `.env` e cria a aplicação:

```php
<?php

use Palacios\Framework\App;
use Palacios\Framework\Hosting\ApplicationEnvironment;

$builder = App::createBuilder(
    dirname(__DIR__),
    $_ENV['APP_ENV'] ?? ApplicationEnvironment::PRODUCTION,
);

$builder->services()
    ->addControllers()
    ->addViews()
    ->addAuthentication()
    ->addAuthorization();

$app = $builder->build();

if ($builder->environment->isDevelopment()) {
    $app->useDevelopmentExceptionPage();
} else {
    $app->useExceptionHandler();
}

$app->mapControllers();

return $app;
```

O arquivo criado pelo comando `new` já contém uma configuração funcional. Habilite somente os middleware necessários ao projeto.

## Criando um controller

Gere o arquivo:

```bash
framework make:controller Users
```

Defina o prefixo e as ações com atributos:

```php
<?php

namespace App\Controllers;

use Palacios\Framework\Results\IActionResult;
use Palacios\Framework\Results\Results;
use Palacios\Framework\Routing\Attributes\HttpGet;
use Palacios\Framework\Routing\Attributes\Route;

#[Route('/users')]
final class UsersController
{
    #[HttpGet(name: 'users.index')]
    public function index(): IActionResult
    {
        return Results::json([
            ['id' => 1, 'name' => 'Ana'],
            ['id' => 2, 'name' => 'Carlos'],
        ]);
    }

    #[HttpGet('/{id:int}', name: 'users.show')]
    public function show(int $id): IActionResult
    {
        return Results::json(['id' => $id]);
    }
}
```

`addControllers()` descobre as classes em `app/Controllers` e `mapControllers()` registra suas rotas.

## Rotas diretas e grupos

Rotas também podem ser declaradas no bootstrap:

```php
$app->mapGet('/status', static fn () => Results::ok([
    'status' => 'online',
]));

$app->mapPost('/users', [UsersController::class, 'store']);
```

Agrupe endpoints que compartilham prefixo ou regras:

```php
$api = $app->mapGroup('/api')
    ->withTags('API');

$v1 = $api->mapGroup('/v1');

$v1->mapGet('/users', [UsersController::class, 'index']);
$v1->mapGet('/users/{id:int}', [UsersController::class, 'show']);
```

Rotas nomeadas permitem gerar URLs sem repetir caminhos:

```php
$url = $app->urlFor('users.show', ['id' => 42]);
```

## Injeção de dependência

Registre contratos e implementações em `bootstrap/app.php`:

```php
$builder->services()
    ->addScoped(IUserRepository::class, UserRepository::class)
    ->addTransient(Mailer::class, Mailer::class)
    ->addSingleton(Cache::class, Cache::class);
```

Tempos de vida disponíveis:

- `addScoped`: uma instância por requisição;
- `addTransient`: uma nova instância a cada resolução;
- `addSingleton`: uma única instância durante a aplicação.

Depois, solicite a dependência pelo construtor:

```php
final readonly class UsersController
{
    public function __construct(
        private IUserRepository $users,
    ) {}
}
```

Interfaces, repositories e services podem ser usados sem DTOs. DTOs são opcionais e úteis quando você quer representar e validar dados de entrada de forma explícita.

## Recebendo dados da requisição

Parâmetros tipados são preenchidos automaticamente por convenção a partir da rota, query string, formulário, JSON ou container:

```php
#[HttpGet('/{id:int}')]
public function show(int $id): IActionResult
{
    return Results::json(['id' => $id]);
}
```

Quando houver ambiguidade, indique a origem:

- `#[FromRoute]`: parâmetro da rota;
- `#[FromQuery]`: query string;
- `#[FromForm]`: formulário;
- `#[FromBody]`: corpo JSON;
- `#[FromHeader]`: cabeçalho HTTP;
- `#[FromServices]`: container de serviços.

Em código bem tipado e sem ambiguidade, esses atributos não são obrigatórios.

## DTOs e validação

Um DTO pode centralizar os dados e suas regras:

```php
use Palacios\Framework\Validation\Attributes\EmailAddress;
use Palacios\Framework\Validation\Attributes\Required;
use Palacios\Framework\Validation\Attributes\StringLength;

final class CreateUserInput
{
    public function __construct(
        #[Required]
        #[StringLength(maximumLength: 120, minimumLength: 2)]
        public string $name,

        #[Required]
        #[EmailAddress]
        public string $email,
    ) {}
}
```

Use DTOs quando eles deixarem a entrada mais clara. Para ações simples, parâmetros escalares tipados ou services injetados continuam válidos.

## Views

Retorne uma view passando seus dados:

```php
return Results::view('users/index', [
    'users' => $this->users->all(),
]);
```

No arquivo `app/Views/users/index.php`:

```php
<h1>Usuários</h1>

<?php foreach ($users as $user): ?>
    <p><?= e($user->name) ?></p>
<?php endforeach; ?>
```

O helper `e()` escapa conteúdo para HTML. Os layouts ficam, por padrão, em `app/Views/layouts`.

## Formulários e proteção antiforgery

Ative o middleware antes de mapear os controllers:

```php
$app->useAntiforgery();
$app->mapControllers();
```

Inclua o campo oculto em formulários que alteram dados:

```php
<form method="post" action="/users">
    <?= csrf_input() ?>

    <input type="text" name="name">
    <button type="submit">Salvar</button>
</form>
```

O framework valida automaticamente o token nas requisições protegidas. O campo interno `_csrf` não é incluído no model binding, portanto o controller recebe apenas os dados do formulário e não precisa remover o token manualmente.

## Autenticação e autorização

Registre os serviços:

```php
$builder->services()
    ->addAuthentication(
        static fn (AuthenticationOptions $options) => $options
            ->loginPath('/login')
            ->accessDeniedPath('/acesso-negado'),
    )
    ->addAuthorization();
```

Adicione os middleware nesta ordem:

```php
$app->useAuthentication();
$app->useAuthorization();
```

Proteja endpoints:

```php
$app->mapGet('/conta', $handler)
    ->requireAuthorization();

$app->mapGet('/admin', $handler)
    ->requireAuthorization('admin');

$app->mapGet('/entrar', $loginHandler)
    ->allowAnonymous();
```

Controllers também podem usar `#[Authorize]`. Sem páginas de interface configuradas, acessos negados retornam `401` ou `403`; com `loginPath()` e `accessDeniedPath()`, aplicações web recebem redirecionamentos.

## Middleware

Crie um middleware:

```bash
framework make:middleware RequestTrace
```

Registre middleware globais no pipeline ou associe-os a grupos de rotas:

```php
$api = $app->mapGroup('/api')
    ->addMiddleware(RequestTraceMiddleware::class);
```

Use middleware para responsabilidades transversais, como logs, autenticação, autorização, antiforgery, cabeçalhos e tratamento de erros.

## Banco de dados

Configure o `.env`:

```dotenv
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=minha_aplicacao
DB_USERNAME=root
DB_PASSWORD=secret
```

Também é possível informar uma DSN completa:

```dotenv
DB_DSN=sqlite:database/database.sqlite
```

Registre a conexão:

```php
$builder->services()->addDatabase(
    static fn (DatabaseOptions $options) => $options
        ->dsn($_ENV['DB_DSN'])
        ->username($_ENV['DB_USERNAME'] ?? null)
        ->password($_ENV['DB_PASSWORD'] ?? null),
);
```

Repositories podem receber `PDO` diretamente pelo construtor.

## Migrations

Gere uma migration:

```bash
framework make:migration CreateUsers
```

Implemente as alterações:

```php
<?php

use Palacios\Framework\Database\Migration;

return new class implements Migration {
    public function up(PDO $connection): void
    {
        $connection->exec(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(120) NOT NULL)',
        );
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE users');
    }
};
```

Aplique as migrations pendentes:

```bash
framework database:update
```

O histórico das migrations executadas fica na tabela `framework_migrations`.

## Geradores disponíveis

```bash
framework make:controller Admin/Users
framework make:model User
framework make:service User
framework make:repository User
framework make:middleware RequestTrace
framework make:migration CreateUsers
```

Os geradores usam namespaces PSR-4 e não sobrescrevem arquivos existentes. `make:repository` cria a interface e a implementação.

## Resultados HTTP

Handlers e controllers podem retornar implementações de `IActionResult`:

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

Exceções HTTP são convertidas em respostas `application/problem+json`. Detalhes internos ficam visíveis somente no ambiente de desenvolvimento.

## Executando com Docker

O projeto inicial inclui `Dockerfile` e `docker-compose.yml`:

```bash
docker compose up --build
```

Ajuste as variáveis do banco no `.env` conforme o ambiente utilizado.

## Testes

Execute os testes da aplicação:

```bash
composer test:smoke
```

Para testes de integração, use `WebApplicationFactory`:

```php
$factory = new WebApplicationFactory(
    static function (WebApplication $app): void {
        $app->mapGet('/ping', static fn () =>
            Results::json(['status' => 'ok'])
        );
    },
);

$response = $factory->createClient()->get('/ping');
```

## Ambientes

Defina o ambiente no `.env`:

```dotenv
APP_ENV=Development
```

Valores comuns:

- `Development`: página detalhada de exceções;
- `Production`: respostas seguras sem detalhes internos.

Nunca habilite a página detalhada de exceções em produção.

## Servidor web em produção

Configure Apache, Nginx ou outro servidor para usar `public/` como document root. Não exponha a raiz do projeto, o arquivo `.env`, `vendor/` ou `app/` diretamente pela web.

O servidor embutido do PHP é indicado apenas para desenvolvimento local:

```bash
php -S localhost:8000 -t public
```

## Atualizando o framework

Verifique versões disponíveis:

```bash
composer outdated palacios/framework
```

Atualize dentro da restrição permitida pelo projeto:

```bash
composer update palacios/framework
```

Leia o changelog antes de atualizar entre versões menores enquanto o projeto estiver na série `0.x`.

## Ajuda e segurança

- Consulte o [CHANGELOG](CHANGELOG.md) para alterações por versão.
- Consulte a [política de segurança](SECURITY.md) para reportar vulnerabilidades.
- O projeto é distribuído sob a [licença MIT](LICENSE).

A série `0.x` representa a fase inicial da API. Mudanças incompatíveis podem ocorrer em versões menores até a estabilização da versão `1.0.0`.
