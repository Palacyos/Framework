<?php

namespace Palacios\Framework;

use Closure;
use PDO;
use Palacios\Framework\Database\DatabaseConnection;
use Palacios\Framework\Database\DatabaseOptions;
use Palacios\Framework\Views\ViewOptions;
use Palacios\Framework\Views\ViewRenderer;
use Palacios\Framework\Security\Authentication\AuthenticationFailureHandler;
use Palacios\Framework\Security\Authentication\AuthenticationOptions;
use Palacios\Framework\Security\Authentication\DefaultAuthenticationFailureHandler;
use Palacios\Framework\Security\Authentication\SessionAuthentication;
use Palacios\Framework\Security\Authorization\AuthorizationOptions;
use Palacios\Framework\Security\Authorization\AuthorizationService;

/** @phpstan-type Binding array{concrete: string|Closure, lifetime: 'singleton'|'scoped'|'transient'} */
final class ServiceCollection
{
    /** @var array<string, Binding> */
    private array $bindings = [];

    /** @var list<array{directory: string, namespace: string}> */
    private array $controllerSources = [];

    public function __construct(private readonly string $basePath = '')
    {
    }

    public function addSingleton(string $abstract, string|Closure $concrete): self
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'lifetime' => 'singleton'];
        return $this;
    }

    public function addScoped(string $abstract, string|Closure $concrete): self
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'lifetime' => 'scoped'];
        return $this;
    }

    public function addTransient(string $abstract, string|Closure $concrete): self
    {
        $this->bindings[$abstract] = ['concrete' => $concrete, 'lifetime' => 'transient'];
        return $this;
    }

    public function addControllers(
        string $directory = 'app/Controllers',
        string $namespace = 'App\\Controllers',
    ): self {
        $this->controllerSources[] = [
            'directory' => trim($directory, '/\\'),
            'namespace' => trim($namespace, '\\'),
        ];
        return $this;
    }

    public function addDatabase(callable $configure): self
    {
        $options = new DatabaseOptions();
        $configure($options);

        $this->addSingleton(
            DatabaseOptions::class,
            static fn (): DatabaseOptions => $options,
        );

        return $this->addSingleton(
            PDO::class,
            static fn (): PDO => DatabaseConnection::create($options),
        );
    }

    public function addViews(?callable $configure = null): self
    {
        $options = new ViewOptions();
        if ($configure !== null) {
            $configure($options);
        }

        $path = $options->getPath();
        if (!$this->isAbsolutePath($path)) {
            $options->path(rtrim($this->basePath, '/\\') . '/' . $path);
        }

        $this->addSingleton(
            ViewOptions::class,
            static fn (): ViewOptions => $options,
        );

        return $this->addSingleton(ViewRenderer::class, ViewRenderer::class);
    }
    public function addAuthentication(?callable $configure = null): self
    {
        $options = new AuthenticationOptions();
        if ($configure !== null) {
            $configure($options);
        }

        $this->addSingleton(
            AuthenticationOptions::class,
            static fn (): AuthenticationOptions => $options,
        );
        $this->addSingleton(SessionAuthentication::class, SessionAuthentication::class);

        return $this->addSingleton(
            AuthenticationFailureHandler::class,
            DefaultAuthenticationFailureHandler::class,
        );
    }

    public function addAuthorization(?callable $configure = null): self
    {
        $options = new AuthorizationOptions();
        if ($configure !== null) {
            $configure($options);
        }

        $this->addSingleton(
            AuthorizationOptions::class,
            static fn (): AuthorizationOptions => $options,
        );

        return $this->addSingleton(AuthorizationService::class, AuthorizationService::class);
    }

    /** @return array<string, Binding> */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /** @return list<array{directory: string, namespace: string}> */
    public function getControllerSources(): array
    {
        return $this->controllerSources;
    }
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
