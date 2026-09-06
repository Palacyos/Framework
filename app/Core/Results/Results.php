<?php
declare(strict_types=1);
namespace App\Core\Results;

final class Results
{
    public static function ok(mixed $value = null): IActionResult
    {
        return $value === null ? new ContentResult('', status: 200) : new JsonResult($value);
    }

    public static function json(mixed $value, int $status = 200): IActionResult { return new JsonResult($value, $status); }
    public static function created(string $location, mixed $value): IActionResult { return new JsonResult($value, 201, ['Location' => $location]); }
    public static function noContent(): IActionResult { return new ContentResult('', status: 204); }
    public static function badRequest(mixed $error): IActionResult { return new JsonResult($error, 400); }
    public static function unauthorized(): IActionResult { return new ContentResult('', status: 401); }
    public static function forbid(): IActionResult { return new ContentResult('', status: 403); }
    public static function notFound(string $message = '404 Not Found'): IActionResult { return new NotFoundResult($message); }
    public static function redirect(string $url, int $status = 302): IActionResult { return new RedirectResult($url, $status); }
    /** @param array<string, mixed> $data */
    public static function view(string $view, array $data = [], string $layout = 'app'): IActionResult { return new ViewResult($view, $data, $layout); }
}
