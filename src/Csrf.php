<?php

namespace Palacios\Framework;

use Palacios\Framework\Http\HttpException;
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
        $token = Session::get('_csrf');
        assert(is_string($token));
        return $token;
    }

    public static function verify(?string $token): void
    {
        $expected = Session::get('_csrf');
        if (!is_string($expected) || !is_string($token) || !hash_equals($expected, $token)) {
            throw new HttpException(419, 'O token antiforgery é inválido ou expirou.');
        }
    }
}
