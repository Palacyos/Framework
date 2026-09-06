<?php

namespace App\Core;

final class Auth
{
    public static function check(): bool
    {
        return Session::get('auth_id') !== null;
    }

    /** @param array<string, mixed> $extra */
    public static function login(int $id, string $type, array $extra = []): void
    {
        Session::regenerate();
        Session::set('auth_id',   $id);
        Session::set('auth_type', $type);

        foreach ($extra as $k => $v) {
            Session::set('auth_' . $k, $v);
        }

        Session::set('logged_in', true);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function id(): ?int
    {
        $id = Session::get('auth_id');
        return is_numeric($id) ? (int) $id : null;
    }

    public static function type(): ?string
    {
        $type = Session::get('auth_type');
        return is_string($type) ? $type : null;
    }

    public static function is(string $type): bool
    {
        return self::type() === $type;
    }
}
