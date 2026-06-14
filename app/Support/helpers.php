<?php

use App\Core\Csrf;
use Random\RandomException;

if (!function_exists('csrf_input')) {
    /**
     * @throws RandomException
     */
    function csrf_input(): string
    {
        $token = Csrf::token();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token) . '">';
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit();
    }
}
