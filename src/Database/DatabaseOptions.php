<?php

declare(strict_types=1);

namespace Palacios\Framework\Database;

use PDO;

final class DatabaseOptions
{
    private ?string $dsn = null;
    private ?string $username = null;
    private ?string $password = null;
    /** @var array<int, mixed> */
    private array $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    public function dsn(string $dsn): self { $this->dsn = $dsn; return $this; }
    public function username(?string $username): self { $this->username = $username; return $this; }
    public function password(?string $password): self { $this->password = $password; return $this; }

    /** @param array<int, mixed> $options */
    public function options(array $options): self
    {
        $this->options = $options + $this->options;
        return $this;
    }

    public function getDsn(): string
    {
        return $this->dsn ?? throw new \LogicException('O DSN do banco de dados não foi configurado.');
    }

    public function getUsername(): ?string { return $this->username; }
    public function getPassword(): ?string { return $this->password; }

    /** @return array<int, mixed> */
    public function getOptions(): array { return $this->options; }
}
