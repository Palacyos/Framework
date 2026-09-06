<?php

namespace App\Core;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                self::env('DB_HOST'),
                self::env('DB_PORT'),
                self::env('DB_DATABASE')
            );

            try {
                self::$instance = new PDO($dsn, self::env('DB_USERNAME'), self::env('DB_PASSWORD'), [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                die('Erro na conexão: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    private static function env(string $key): string
    {
        $value = $_ENV[$key] ?? null;
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \RuntimeException("Variável de ambiente ausente ou inválida: {$key}");
        }
        return (string) $value;
    }
}
