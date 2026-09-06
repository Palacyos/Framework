<?php
declare(strict_types=1);
namespace App\Middlewares;
use App\Core\Http\HttpContext;
use App\Core\Logging\JsonConsoleLogger;
use App\Core\Logging\Logger;
final readonly class RequestLoggingMiddleware
{
    public function __construct(private Logger $logger = new JsonConsoleLogger('HTTP')) {}
    public function handle(HttpContext $context, callable $next): void
    {
        $started = hrtime(true);
        try { $next(); }
        finally {
            $this->logger->info('HTTP request completed', [
                'traceId' => $context->traceId,
                'method' => $context->request->method,
                'path' => $context->request->path,
                'statusCode' => $context->response->statusCode(),
                'elapsedMs' => round((hrtime(true) - $started) / 1_000_000, 2),
            ]);
        }
    }
}
