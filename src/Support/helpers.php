<?php

declare(strict_types=1);

use Palacios\Framework\Csrf;

if (!function_exists('csrf_input')) {
    /**
     * Gera o campo oculto antiforgery usando o token da sessão.
     *
     * O argumento opcional existe apenas para compatibilidade com aplicações
     * que geravam o token no controller antes de o helper pertencer ao pacote.
     */
    function csrf_input(?string $token = null): string
    {
        $token ??= Csrf::token();
        if ($token === '') {
            throw new InvalidArgumentException('O token antiforgery não pode ser vazio.');
        }

        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }
}
