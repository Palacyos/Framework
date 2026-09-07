<?php

declare(strict_types=1);

use Palacios\Framework\Container;
use Palacios\Framework\Database\DatabaseOptions;
use Palacios\Framework\ServiceCollection;
use Palacios\Framework\Views\ViewOptions;
use Palacios\Framework\Views\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class InfrastructureOptionsTest extends TestCase
{
    public function testViewRendererUsesConfiguredPathAndDefaultLayout(): void
    {
        $directory = sys_get_temp_dir() . '/palacios-views-' . bin2hex(random_bytes(6));
        mkdir($directory . '/layouts', 0777, true);
        file_put_contents($directory . '/hello.php', 'Olá, <?= $name ?>');
        file_put_contents($directory . '/layouts/main.php', '<main><?= $content ?></main>');

        try {
            $renderer = new ViewRenderer(
                (new ViewOptions())->path($directory)->defaultLayout('main'),
            );

            self::assertSame(
                '<main>Olá, Ada</main>',
                $renderer->render('hello', ['name' => 'Ada']),
            );
        } finally {
            unlink($directory . '/layouts/main.php');
            unlink($directory . '/hello.php');
            rmdir($directory . '/layouts');
            rmdir($directory);
        }
    }

    public function testDatabaseRequiresExplicitDsn(): void
    {
        $this->expectException(LogicException::class);
        (new DatabaseOptions())->getDsn();
    }

    public function testDatabaseFailureIsPropagatedAsException(): void
    {
        $services = (new ServiceCollection())
            ->addDatabase(static fn (DatabaseOptions $options) => $options->dsn('driver-inexistente:'));

        $container = new Container($services->getBindings());

        $this->expectException(PDOException::class);
        $container->make(PDO::class);
    }
}
