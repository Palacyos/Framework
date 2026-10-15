<?php
declare(strict_types=1);
namespace Palacios\Framework\Middleware;
use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Logging\JsonConsoleLogger;
use Palacios\Framework\Logging\Logger;
final readonly class RequestLoggingMiddleware
{
    private const string CONTEXT_ITEM_KEY = self::class . '.registered';

    public function __construct(private Logger $logger = new JsonConsoleLogger('HTTP')) {}
    public function handle(HttpContext $context, callable $next): void
    {
        if (($context->items[self::CONTEXT_ITEM_KEY] ?? false) === true) {
            $next();
            return;
        }

        $context->items[self::CONTEXT_ITEM_KEY] = true;
        $started = hrtime(true);
        $context->response->onCompleted(function () use ($context, $started): void {
            $this->logger->info('HTTP request completed', [
                'traceId' => $context->traceId,
                'method' => $context->request->method,
                'path' => $context->request->path,
                'statusCode' => $context->response->statusCode(),
                'elapsedMs' => round((hrtime(true) - $started) / 1_000_000, 2),
            ]);
        });
        $next();
    }
}
