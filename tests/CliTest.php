<?php

declare(strict_types=1);

use Palacios\Framework\Console\ConsoleApplication;
use Palacios\Framework\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    private string $temporaryDirectory;
    private string $previousDirectory;

    protected function setUp(): void
    {
        $this->previousDirectory = getcwd() ?: '/';
        $this->temporaryDirectory = sys_get_temp_dir() . '/palacios-cli-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        chdir($this->previousDirectory);
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testNewCreatesIndependentProjectWithoutDevelopmentRepository(): void
    {
        chdir($this->temporaryDirectory);
        $application = new ConsoleApplication(dirname(__DIR__));

        self::assertSame(0, $application->run(['framework', 'new', 'Blog', '--no-install']));

        $project = $this->temporaryDirectory . '/Blog';
        self::assertFileExists($project . '/bootstrap/app.php');
        self::assertFileExists($project . '/.env');
        self::assertDirectoryDoesNotExist($project . '/vendor');
        self::assertStringContainsString('APP_KEY=base64:', (string) file_get_contents($project . '/.env'));

        $composer = json_decode((string) file_get_contents($project . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('repositories', $composer);
        self::assertMatchesRegularExpression(
            '/^(?:dev-main|\^\d+\.\d+)$/',
            $composer['require']['palacios/framework'],
        );
    }

    public function testGeneratorsCreateNamespacedApplicationFiles(): void
    {
        chdir($this->temporaryDirectory);
        $application = new ConsoleApplication(dirname(__DIR__));

        self::assertSame(0, $application->run(['framework', 'make:controller', 'Admin/User']));
        self::assertSame(0, $application->run(['framework', 'make:model', 'User']));
        self::assertSame(0, $application->run(['framework', 'make:service', 'Billing']));
        self::assertSame(0, $application->run(['framework', 'make:repository', 'User']));
        self::assertSame(0, $application->run(['framework', 'make:middleware', 'Trace']));
        self::assertSame(0, $application->run(['framework', 'make:migration', 'CreateUsers']));

        self::assertFileExists($this->temporaryDirectory . '/app/Controllers/Admin/UserController.php');
        self::assertFileExists($this->temporaryDirectory . '/app/Models/User.php');
        self::assertFileExists($this->temporaryDirectory . '/app/Services/BillingService.php');
        self::assertFileExists($this->temporaryDirectory . '/app/Repositories/Interfaces/IUserRepository.php');
        self::assertFileExists($this->temporaryDirectory . '/app/Repositories/UserRepository.php');
        self::assertFileExists($this->temporaryDirectory . '/app/Middleware/TraceMiddleware.php');
        self::assertCount(1, glob($this->temporaryDirectory . '/database/migrations/*_create_users.php') ?: []);
    }

    public function testMigratorAppliesEachMigrationOnlyOnce(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite não está disponível.');
        }

        $migrationDirectory = $this->temporaryDirectory . '/migrations';
        mkdir($migrationDirectory, 0775, true);
        $migration = <<<'PHP'
<?php
use Palacios\Framework\Database\Migration;
return new class implements Migration {
    public function up(PDO $connection): void
    {
        $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
    }
    public function down(PDO $connection): void {}
};
PHP;
        file_put_contents($migrationDirectory . '/20260101_000000_create_users.php', $migration);

        $connection = new PDO('sqlite::memory:');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $migrator = new Migrator($connection, $migrationDirectory);

        self::assertSame(['20260101_000000_create_users'], $migrator->migrate());
        self::assertSame([], $migrator->migrate());
        self::assertSame('users', $connection->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='users'",
        )?->fetchColumn());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
