<?php

declare(strict_types=1);

namespace Palacios\Framework\Console;

use Composer\InstalledVersions;
use Palacios\Framework\Database\Migrator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

final readonly class ConsoleApplication
{
    public function __construct(private string $packageRoot) {}

    /** @param list<string> $arguments */
    public function run(array $arguments): int
    {
        $command = $arguments[1] ?? 'help';

        try {
            return match ($command) {
                'new' => $this->newProject($arguments),
                'make:controller' => $this->generate($arguments, 'controller'),
                'make:model' => $this->generate($arguments, 'model'),
                'make:service' => $this->generate($arguments, 'service'),
                'make:repository' => $this->generate($arguments, 'repository'),
                'make:middleware' => $this->generate($arguments, 'middleware'),
                'make:migration' => $this->makeMigration($arguments),
                'database:update', 'db:update' => $this->updateDatabase($arguments),
                'help', '--help', '-h' => $this->help(),
                '--version', '-V' => $this->version(),
                default => throw new RuntimeException("Comando desconhecido: {$command}"),
            };
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return 1;
        }
    }

    /** @param list<string> $arguments */
    private function newProject(array $arguments): int
    {
        $name = $arguments[2] ?? '';
        if ($name === '' || str_starts_with($name, '-')) {
            throw new RuntimeException('Uso: framework new <diretório> [--no-install]');
        }

        $source = $this->packageRoot . '/resources/skeleton';
        if (!is_dir($source)) {
            throw new RuntimeException('Template da aplicação não foi encontrado no pacote.');
        }

        $target = $this->absolutePath($name);
        if (is_dir($target) && $this->directoryHasEntries($target)) {
            throw new RuntimeException("O diretório de destino não está vazio: {$target}");
        }
        if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
            throw new RuntimeException("Não foi possível criar o diretório: {$target}");
        }

        $this->copyTemplate($source, $target);
        $this->prepareProjectComposer($target . '/composer.json');
        $this->prepareEnvironment($target);

        $this->line("Projeto criado em {$target}");

        if (!in_array('--no-install', $arguments, true)) {
            $this->line('Instalando dependências...');
            passthru('composer install --working-dir=' . escapeshellarg($target) . ' --no-interaction', $exitCode);
            if ($exitCode !== 0) {
                throw new RuntimeException('O projeto foi criado, mas o Composer não conseguiu instalar as dependências.');
            }
        }

        $this->line('Pronto. Execute:');
        $this->line("  cd {$name}");
        $this->line('  php -S localhost:8000 -t public');
        return 0;
    }

    /** @param list<string> $arguments */
    private function generate(array $arguments, string $type): int
    {
        $input = $arguments[2] ?? '';
        if ($input === '') {
            throw new RuntimeException("Informe o nome para make:{$type}.");
        }

        [$namespaceSuffix, $class] = $this->className($input);
        $base = getcwd() ?: '.';

        if ($type === 'repository') {
            $interface = str_starts_with($class, 'I') ? $class : 'I' . $class;
            $implementation = str_starts_with($class, 'I') ? substr($class, 1) : $class;
            $implementation = str_ends_with($implementation, 'Repository')
                ? $implementation
                : $implementation . 'Repository';
            $interface = str_ends_with($interface, 'Repository') ? $interface : $interface . 'Repository';

            $interfaceNamespace = 'App\\Repositories\\Interfaces' . $namespaceSuffix;
            $implementationNamespace = 'App\\Repositories' . $namespaceSuffix;
            $interfacePath = $base . '/app/Repositories/Interfaces'
                . str_replace('\\', '/', $namespaceSuffix) . "/{$interface}.php";
            $implementationPath = $base . '/app/Repositories'
                . str_replace('\\', '/', $namespaceSuffix) . "/{$implementation}.php";

            $this->writeNew($interfacePath, $this->repositoryInterface($interfaceNamespace, $interface));
            $this->writeNew(
                $implementationPath,
                $this->repository($implementationNamespace, $implementation, $interfaceNamespace, $interface),
            );
            $this->line("Criados: {$interfacePath} e {$implementationPath}");
            return 0;
        }

        $configuration = match ($type) {
            'controller' => ['Controllers', str_ends_with($class, 'Controller') ? $class : $class . 'Controller'],
            'model' => ['Models', $class],
            'service' => ['Services', str_ends_with($class, 'Service') ? $class : $class . 'Service'],
            'middleware' => ['Middleware', str_ends_with($class, 'Middleware') ? $class : $class . 'Middleware'],
            default => throw new RuntimeException("Gerador inválido: {$type}"),
        };

        [$directory, $class] = $configuration;
        $namespace = "App\\{$directory}{$namespaceSuffix}";
        $path = $base . "/app/{$directory}" . str_replace('\\', '/', $namespaceSuffix) . "/{$class}.php";
        $contents = match ($type) {
            'controller' => $this->controller($namespace, $class),
            'model' => $this->plainClass($namespace, $class),
            'service' => $this->plainClass($namespace, $class),
            'middleware' => $this->middleware($namespace, $class),
        };

        $this->writeNew($path, $contents);
        $this->line("Criado: {$path}");
        return 0;
    }

    /** @param list<string> $arguments */
    private function makeMigration(array $arguments): int
    {
        $name = $arguments[2] ?? '';
        if ($name === '') {
            throw new RuntimeException('Informe o nome da migration.');
        }

        $slug = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', str_replace(['-', ' '], '_', $name)));
        $slug = trim((string) preg_replace('/[^a-z0-9_]+/', '_', $slug), '_');
        if ($slug === '') {
            throw new RuntimeException('Nome de migration inválido.');
        }

        $directory = (getcwd() ?: '.') . '/database/migrations';
        $path = $directory . '/' . gmdate('Ymd_His') . "_{$slug}.php";
        $this->writeNew($path, $this->migration());
        $this->line("Criada: {$path}");
        return 0;
    }

    /** @param list<string> $arguments */
    private function updateDatabase(array $arguments): int
    {
        $base = getcwd() ?: '.';
        $this->loadEnvironment($base . '/.env');
        $dsn = getenv('DB_DSN') ?: $this->buildDsn();
        $username = getenv('DB_USERNAME') ?: null;
        $password = getenv('DB_PASSWORD') ?: null;

        $connection = new PDO($dsn, $username ?: null, $password ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $executed = (new Migrator($connection, $base . '/database/migrations'))->migrate();

        if ($executed === []) {
            $this->line('Banco de dados já está atualizado.');
            return 0;
        }

        foreach ($executed as $migration) {
            $this->line("Aplicada: {$migration}");
        }
        return 0;
    }

    private function buildDsn(): string
    {
        $driver = getenv('DB_DRIVER') ?: 'mysql';
        if ($driver === 'sqlite') {
            $database = getenv('DB_DATABASE') ?: 'database/database.sqlite';
            return 'sqlite:' . $this->absolutePath($database);
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: 'app';
        return "{$driver}:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    }

    private function help(): int
    {
        $this->line('Palacios Framework CLI');
        $this->line('');
        $this->line('  new <diretório> [--no-install]  Cria uma aplicação');
        $this->line('  make:controller <nome>          Cria um controller');
        $this->line('  make:model <nome>               Cria um model');
        $this->line('  make:service <nome>             Cria um service');
        $this->line('  make:repository <nome>          Cria interface e repository');
        $this->line('  make:middleware <nome>          Cria um middleware');
        $this->line('  make:migration <nome>           Cria uma migration');
        $this->line('  database:update                 Aplica migrations pendentes');
        return 0;
    }

    private function version(): int
    {
        $this->line('Palacios Framework ' . $this->packageVersion());
        return 0;
    }

    private function copyTemplate(string $source, string $target): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $parts = explode(DIRECTORY_SEPARATOR, $relative);
            if (in_array('vendor', $parts, true) || in_array('.git', $parts, true)
                || $relative === 'composer.lock' || $relative === '.env') {
                continue;
            }

            $destination = $target . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
                    throw new RuntimeException("Não foi possível criar: {$destination}");
                }
            } else {
                $directory = dirname($destination);
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException("Não foi possível criar: {$directory}");
                }
                if (!copy($item->getPathname(), $destination)) {
                    throw new RuntimeException("Não foi possível copiar: {$relative}");
                }
            }
        }
    }

    private function prepareProjectComposer(string $path): void
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('composer.json do template é inválido.');
        }
        $composer = $decoded;
        $requirements = $composer['require'] ?? [];
        if (!is_array($requirements)) {
            throw new RuntimeException('A seção require do template é inválida.');
        }
        $requirements['palacios/framework'] = $this->packageConstraint();
        $composer['require'] = $requirements;
        unset($composer['repositories']);
        if ($this->packageConstraint() !== 'dev-main') {
            unset($composer['minimum-stability'], $composer['prefer-stable']);
        }
        file_put_contents($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function prepareEnvironment(string $target): void
    {
        $example = $target . '/.env.example';
        $environment = $target . '/.env';
        $contents = is_file($example) ? (string) file_get_contents($example) : "APP_ENV=Development\n";
        if (!str_contains($contents, 'APP_KEY=')) {
            $contents .= "\nAPP_KEY=base64:" . base64_encode(random_bytes(32)) . "\n";
        }
        file_put_contents($environment, $contents);
    }

    private function packageConstraint(): string
    {
        $version = $this->packageVersion();
        if (preg_match('/^v?(\d+)\.(\d+)\./', $version, $matches) === 1) {
            return "^{$matches[1]}.{$matches[2]}";
        }
        return 'dev-main';
    }

    private function packageVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('palacios/framework')) {
            $version = InstalledVersions::getPrettyVersion('palacios/framework');
            if (is_string($version)) {
                return $version;
            }
        }
        return 'dev-main';
    }

    /** @return array{string, string} */
    private function className(string $input): array
    {
        $input = trim(str_replace('\\', '/', $input), '/');
        if ($input === '' || preg_match('#(^|/)\.\.(/|$)#', $input)) {
            throw new RuntimeException('Nome de classe inválido.');
        }

        $parts = explode('/', $input);
        foreach ($parts as &$part) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) !== 1) {
                throw new RuntimeException("Nome de classe inválido: {$input}");
            }
            $part = ucfirst($part);
        }
        unset($part);

        $class = array_pop($parts);
        $suffix = $parts === [] ? '' : '\\' . implode('\\', $parts);
        return [$suffix, $class];
    }

    private function writeNew(string $path, string $contents): void
    {
        if (file_exists($path)) {
            throw new RuntimeException("O arquivo já existe: {$path}");
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Não foi possível criar: {$directory}");
        }
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Não foi possível escrever: {$path}");
        }
    }

    private function controller(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Palacios\\Framework\\Results\\IActionResult;
use Palacios\\Framework\\Results\\Results;

final class {$class}
{
    public function index(): IActionResult
    {
        return Results::ok();
    }
}

PHP;
    }

    private function plainClass(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

final class {$class}
{
}

PHP;
    }

    private function middleware(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Palacios\\Framework\\Http\\HttpContext;

final class {$class}
{
    public function handle(HttpContext \$context, callable \$next): void
    {
        \$next();
    }
}

PHP;
    }

    private function repositoryInterface(string $namespace, string $interface): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

interface {$interface}
{
}

PHP;
    }

    private function repository(
        string $namespace,
        string $class,
        string $interfaceNamespace,
        string $interface,
    ): string {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use {$interfaceNamespace}\\{$interface};

final class {$class} implements {$interface}
{
}

PHP;
    }

    private function migration(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Palacios\Framework\Database\Migration;

return new class implements Migration {
    public function up(PDO $connection): void
    {
        // $connection->exec('CREATE TABLE ...');
    }

    public function down(PDO $connection): void
    {
        // $connection->exec('DROP TABLE ...');
    }
};

PHP;
    }

    private function loadEnvironment(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim(trim($value), "'\"");
            if ($name !== '' && getenv($name) === false) {
                putenv("{$name}={$value}");
            }
        }
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return rtrim($path, '/\\');
        }
        return rtrim((getcwd() ?: '.') . '/' . $path, '/');
    }

    private function directoryHasEntries(string $path): bool
    {
        $entries = scandir($path);
        return $entries !== false && count($entries) > 2;
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    private function error(string $message): void
    {
        fwrite(STDERR, 'Erro: ' . $message . PHP_EOL);
    }
}
