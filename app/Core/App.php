<?php

namespace App\Core;

final class App
{
    public static function run(): void
    {
        Session::start();

        $container = new Container();
        self::registerBindings($container);

        $router = new Router(
            require __DIR__ . '/../../config/routes.php',
            $container
        );

        $router->dispatch();
    }

    private static function registerBindings(Container $container): void
    {
        // Registre aqui as interfaces e suas implementações concretas.
        // Controllers são resolvidos automaticamente via autowiring.
        //
        // Exemplo:
        // $container->bind(UserInterface::class, fn() => new UserRepository());
    }
}
