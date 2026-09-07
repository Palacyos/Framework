<?php

declare(strict_types=1);

namespace Palacios\Framework\Database;

use PDO;
use RuntimeException;

final readonly class Migrator
{
    public function __construct(
        private PDO $connection,
        private string $migrationPath,
    ) {}

    /** @return list<string> */
    public function migrate(): array
    {
        $this->ensureHistoryTable();
        $applied = $this->applied();
        $executed = [];

        foreach ($this->files() as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (isset($applied[$name])) {
                continue;
            }

            $migration = require $file;
            if (!$migration instanceof Migration) {
                throw new RuntimeException("Migration inválida: {$file}");
            }

            $transactional = in_array($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME), ['pgsql', 'sqlite'], true);
            if ($transactional) $this->connection->beginTransaction();
            try {
                $migration->up($this->connection);
                $statement = $this->connection->prepare(
                    'INSERT INTO framework_migrations (migration, applied_at) VALUES (:migration, :applied_at)',
                );
                $statement->execute([
                    'migration' => $name,
                    'applied_at' => gmdate('Y-m-d H:i:s'),
                ]);
                if ($transactional) $this->connection->commit();
                $executed[] = $name;
            } catch (\Throwable $exception) {
                if ($transactional && $this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
                throw $exception;
            }
        }

        return $executed;
    }

    private function ensureHistoryTable(): void
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS framework_migrations ('
            . 'migration VARCHAR(255) PRIMARY KEY, '
            . 'applied_at VARCHAR(32) NOT NULL'
            . ')',
        );
    }

    /** @return array<string, true> */
    private function applied(): array
    {
        $statement = $this->connection->query('SELECT migration FROM framework_migrations');
        if ($statement === false) {
            return [];
        }

        $result = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $name) {
            if (is_string($name)) {
                $result[$name] = true;
            }
        }
        return $result;
    }

    /** @return list<string> */
    private function files(): array
    {
        if (!is_dir($this->migrationPath)) {
            return [];
        }

        $files = glob(rtrim($this->migrationPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            throw new RuntimeException('Não foi possível listar as migrations.');
        }
        sort($files, SORT_STRING);
        return $files;
    }
}
