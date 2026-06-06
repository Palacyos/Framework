<?php

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set(string $k, mixed $v): void
    {
        self::start();
        $_SESSION[$k] = $v;
    }

    public static function get(string $k): mixed
    {
        self::start();
        return $_SESSION[$k] ?? null;
    }

    public static function remove(string $k): void
    {
        self::start();
        unset($_SESSION[$k]);
    }

    public static function destroy(): void
    {
        self::start();
        session_unset();
        session_destroy();
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }
}
