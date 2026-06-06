<?php

namespace App\Core;

use Random\RandomException;

final class Csrf
{
    /**
     * @throws RandomException
     */
    public static function token(): string
    {
        if (!Session::get('_csrf')) {
            Session::set('_csrf', bin2hex(random_bytes(32)));
        }
        return Session::get('_csrf');
    }

    public static function verify(?string $token): void
    {
        if (!$token || $token !== Session::get('_csrf')) {
            http_response_code(419);
            exit('CSRF inválido');
        }
    }
}
