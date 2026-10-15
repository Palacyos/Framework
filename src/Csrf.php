<?php

namespace Palacios\Framework;

use Palacios\Framework\Http\HttpException;
use Palacios\Framework\Security\Antiforgery\CsrfTokenGenerationException;
use Random\RandomException;

final class Csrf
{
    /** @throws CsrfTokenGenerationException */
    public static function token(): string
    {
        $storedToken = Session::get('_csrf');
        if (is_string($storedToken) && $storedToken !== '') {
            return $storedToken;
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (RandomException $exception) {
            throw new CsrfTokenGenerationException(
                'Não foi possível gerar um token antiforgery seguro.',
                0,
                $exception,
            );
        }

        Session::set('_csrf', $token);
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
