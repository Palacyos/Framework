# PHP Framework

Framework MVC minimalista em PHP 8.2+ — Builder pattern, pipeline de middleware com `next()`, `IActionResult`, DI com lifetimes e binding automático de parâmetros de rota.

## Stack

- PHP 8.2+
- Tailwind CSS 4
- MySQL / MariaDB
- Docker
- Composer

---

## Estrutura de diretórios

```
public/
└── index.php               Ponto de entrada — equivalente ao Program.cs do .NET

app/
├── Controllers/            Recebem dependências via construtor (autowiring automático)
├── Core/
│   ├── App.php             Fábrica do WebApplicationBuilder
│   ├── Auth.php            Autenticação via sessão
│   ├── Connection.php      Singleton PDO
│   ├── Container.php       IoC Container com autowiring, Singleton/Scoped/Transient
│   ├── Csrf.php            Geração e verificação de token CSRF
│   ├── Results/            IActionResult e implementações (View, Json, Redirect…)
│   ├── Router.php          Roteador com pipeline de middleware e binding de params
│   ├── ServiceCollection.php  Registro de serviços com lifetime
│   ├── Session.php         Wrapper de sessão
│   ├── View.php            Renderização de views com layouts
│   ├── WebApplication.php  App construído — mapGet/Post/Put/Delete/Patch + run()
│   └── WebApplicationBuilder.php  Builder — services() + build()
├── Middlewares/            AuthMiddleware, CsrfMiddleware
├── Models/                 Apenas propriedades — sem queries
├── Repositories/
│   ├── Interfaces/         Contratos (IUserRepository.php, etc.)
│   └── *Repository.php     Implementações concretas com PDO
└── Support/
    └── helpers.php         csrf_input(), e(), redirect()
```

---

## Instalação

```bash
# 1. Instalar dependências PHP
composer install

# 2. Instalar dependências front-end
npm install

# 3. Configurar ambiente
cp .env.example .env
# Edite o .env com suas credenciais de banco

# 4. Subir com Docker
docker-compose up -d

# 5. Compilar CSS
npm run dev    # modo watch
npm run build  # produção
```

---

## O ponto de entrada — `public/index.php`

É aqui que tudo é configurado: serviços, middleware e rotas.

```php
<?php
// public/index.php

$builder = App::createBuilder();

// ── Registrar serviços ────────────────────────────────────────────────────────
$builder->services()->addSingleton(IUserRepository::class, UserRepository::class);
$builder->services()->addScoped(IOrderRepository::class, OrderRepository::class);

$app = $builder->build();

// ── Middleware global (executado em todas as rotas) ───────────────────────────
$app->use(CsrfMiddleware::class);

// ── Rotas ─────────────────────────────────────────────────────────────────────
$app->mapGet('/',       fn () => new RedirectResult('/home'));
$app->mapGet('/home',   [HomeController::class, 'index']);
$app->mapGet('/users/{id}', [UserController::class, 'show'], [AuthMiddleware::class]);
$app->mapPost('/users',     [UserController::class, 'store']);

$app->run();
```

---

## Injeção de Dependência

O container resolve dependências automaticamente via Reflection (autowiring). Você registra interfaces com três lifetimes possíveis:

| Método | Comportamento | Equivalente .NET |
|---|---|---|
| `addSingleton` | Uma única instância por toda a vida da aplicação | `AddSingleton` |
| `addScoped` | Uma instância por requisição HTTP | `AddScoped` |
| `addTransient` | Nova instância a cada resolução | `AddTransient` |

```php
$builder->services()->addSingleton(IUserRepository::class, UserRepository::class);
$builder->services()->addScoped(IUserRepository::class, UserRepository::class);
$builder->services()->addTransient(IUserRepository::class, UserRepository::class);

// Também aceita closure para lógica customizada:
$builder->services()->addSingleton(
    IMailer::class,
    fn (Container $c) => new SmtpMailer($_ENV['MAIL_HOST'])
);
```

Controllers e suas dependências são resolvidos automaticamente — basta declarar no construtor:

```php
final class UserController
{
    public function __construct(
        private IUserRepository $users  // injetado automaticamente
    ) {}
}
```

---

## Rotas e binding de parâmetros

Rotas são registradas no `index.php` com os métodos `map*`. Parâmetros de rota entre `{chaves}` são injetados diretamente nos parâmetros da action com cast automático de tipo:

```php
$app->mapGet('/users/{id}',           [UserController::class, 'show']);
$app->mapGet('/posts/{slug}/edit',    [PostController::class, 'edit']);
$app->mapPost('/users',               [UserController::class, 'store']);
$app->mapPut('/users/{id}',           [UserController::class, 'update']);
$app->mapDelete('/users/{id}',        [UserController::class, 'destroy']);
$app->mapPatch('/users/{id}/status',  [UserController::class, 'patchStatus']);
```

```php
// A rota /users/{id} injeta $id automaticamente — sem precisar de $params['id']
public function show(int $id): IActionResult
{
    $user = $this->users->findById($id);
    return new ViewResult('users/show', ['user' => $user]);
}
```

Serviços também podem ser injetados diretamente nos parâmetros de closures (rotas inline):

```php
$app->mapGet('/ping', fn (IUserRepository $users) => new JsonResult($users->count()));
```

### Middleware por rota

Passe um array de middlewares como terceiro argumento. Eles são executados após o middleware global, antes da action:

```php
$app->mapGet('/admin', [AdminController::class, 'index'], [
    AuthMiddleware::class,
    new AuthMiddleware(['admin']),  // ou instância direta com argumentos
]);
```

---

## Controllers

Controllers são classes PHP simples. Declare dependências no construtor e retorne um `IActionResult` nas actions:

```php
final class UserController
{
    public function __construct(
        private IUserRepository $users
    ) {}

    public function index(): IActionResult
    {
        $users = $this->users->all();
        return new ViewResult('users/index', ['users' => $users]);
    }

    public function show(int $id): IActionResult
    {
        $user = $this->users->findById($id);
        return new ViewResult('users/show', ['user' => $user]);
    }

    public function store(): IActionResult
    {
        $this->users->create($_POST);
        return new RedirectResult('/users');
    }

    public function destroy(int $id): IActionResult
    {
        $this->users->delete($id);
        return new JsonResult(['deleted' => true]);
    }
}
```

---

## IActionResult

Todos os resultados de action implementam `IActionResult`. Os disponíveis:

### `ViewResult` — renderiza uma view com layout

```php
return new ViewResult('users/index');
return new ViewResult('users/show', ['user' => $user]);
return new ViewResult('users/show', ['user' => $user], layout: 'auth'); // layout alternativo
```

### `JsonResult` — resposta JSON

```php
return new JsonResult(['id' => 1, 'name' => 'Thales']);
return new JsonResult(['error' => 'Not found'], status: 404);
```

### `RedirectResult` — redirecionamento HTTP

```php
return new RedirectResult('/home');
return new RedirectResult('/login', status: 301);
```

### `ContentResult` — texto ou HTML livre

```php
return new ContentResult('Hello, world!');
return new ContentResult('<b>ok</b>', contentType: 'text/html');
```

### `NotFoundResult` — 404

```php
return new NotFoundResult();
return new NotFoundResult('Usuário não encontrado');
```

---

## Views

Views ficam em `app/Views/` e são arquivos PHP puros. Variáveis passadas pelo controller ficam disponíveis diretamente:

```php
// Controller:
return new ViewResult('users/show', ['user' => $user, 'title' => 'Perfil']);

// app/Views/users/show.php:
<h1><?= e($title) ?></h1>
<p>Nome: <?= e($user->name) ?></p>
```

O conteúdo da view é injetado em `$content` dentro do layout:

```php
// app/Views/layouts/app.php:
<!doctype html>
<html lang="pt-br">
<head>
    <title>App</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <?= $content ?>
</body>
</html>
```

Layouts ficam em `app/Views/layouts/`. O padrão é `app`. Para usar outro:

```php
return new ViewResult('auth/login', [], layout: 'auth');
```

---

## Middleware

Middlewares implementam um método `handle(callable $next): void`. Chame `$next()` para passar para o próximo estágio do pipeline. Não chamar `$next()` interrompe a requisição.

```php
final class AuthMiddleware
{
    public function handle(callable $next): void
    {
        if (!Auth::check()) {
            (new RedirectResult('/login'))->execute();
        }

        $next(); // continua para o próximo middleware ou para a action
    }
}
```

Pipeline com múltiplos middlewares — a ordem de execução segue a ordem de registro:

```php
// Ordem: LogMiddleware → AuthMiddleware → action
$app->mapGet('/dashboard', [DashboardController::class, 'index'], [
    LogMiddleware::class,
    AuthMiddleware::class,
]);
```

O middleware global (via `$app->use()`) sempre executa antes dos middlewares de rota.

---

## Autenticação

```php
// Login
Auth::login(id: $user->id, type: 'admin', extra: ['name' => $user->name]);

// Logout
Auth::logout();

// Verificações
Auth::check();           // bool — usuário está logado?
Auth::id();              // ?int — ID do usuário logado
Auth::type();            // ?string — tipo do usuário ('admin', 'user', etc.)
Auth::is('admin');       // bool — é do tipo especificado?
```

### Proteger rotas com `AuthMiddleware`

```php
// Qualquer usuário autenticado:
$app->mapGet('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

// Apenas usuários do tipo 'admin':
$app->mapGet('/admin', [AdminController::class, 'index'], [
    new AuthMiddleware(['admin'])
]);
```

---

## CSRF

Adicione o token em todo formulário POST:

```php
<form method="POST" action="/users">
    <?= csrf_input() ?>
    <input type="text" name="name">
    <button type="submit">Salvar</button>
</form>
```

Proteja as rotas POST/PUT/PATCH/DELETE com `CsrfMiddleware`:

```php
$app->mapPost('/users', [UserController::class, 'store'], [CsrfMiddleware::class]);
```

---

## Helpers globais

```php
e($value)          // escapa HTML — use em todas as saídas de dados do usuário
csrf_input()       // gera <input type="hidden" name="_csrf" value="...">
redirect('/rota')  // redireciona e encerra — atalho para RedirectResult fora de controllers
```

---

## Como adicionar uma feature completa

**Exemplo: CRUD de usuários**

### 1. Model

```php
// app/Models/User.php
final class User
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}
```

### 2. Interface do repositório

```php
// app/Repositories/Interfaces/IUserRepository.php
interface IUserRepository
{
    public function all(): array;
    public function findById(int $id): ?User;
    public function create(array $data): User;
    public function delete(int $id): void;
}
```

### 3. Repositório concreto

```php
// app/Repositories/UserRepository.php
final class UserRepository implements IUserRepository
{
    public function __construct(private Connection $db) {}

    public function all(): array
    {
        $stmt = $this->db->get()->query('SELECT * FROM users');
        return $stmt->fetchAll(PDO::FETCH_CLASS, User::class);
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->get()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchObject(User::class) ?: null;
    }

    // ...
}
```

### 4. Registrar no container

```php
// public/index.php
$builder->services()->addScoped(IUserRepository::class, UserRepository::class);
```

### 5. Controller

```php
// app/Controllers/UserController.php
final class UserController
{
    public function __construct(private IUserRepository $users) {}

    public function index(): IActionResult
    {
        return new ViewResult('users/index', ['users' => $this->users->all()]);
    }

    public function show(int $id): IActionResult
    {
        $user = $this->users->findById($id);
        return $user
            ? new ViewResult('users/show', ['user' => $user])
            : new NotFoundResult('Usuário não encontrado');
    }
}
```

### 6. Rotas

```php
// public/index.php
$app->mapGet('/users',      [UserController::class, 'index'], [AuthMiddleware::class]);
$app->mapGet('/users/{id}', [UserController::class, 'show'],  [AuthMiddleware::class]);
```

### 7. Views

```
app/Views/users/index.php
app/Views/users/show.php
```

---

## Banco de dados

`Connection` é um Singleton que entrega uma instância PDO configurada via `.env`:

```php
final class UserRepository
{
    public function __construct(private Connection $db) {}

    public function findById(int $id): ?User
    {
        $stmt = $this->db->get()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchObject(User::class) ?: null;
    }
}
```

Variáveis de ambiente necessárias no `.env`:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=meu_banco
DB_USER=root
DB_PASS=senha
```
