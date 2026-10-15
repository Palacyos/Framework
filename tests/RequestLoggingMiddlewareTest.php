<?php

declare(strict_types=1);

use Palacios\Framework\App;
use Palacios\Framework\Csrf;
use Palacios\Framework\Filters\ExceptionContext;
use Palacios\Framework\Filters\IExceptionFilter;
use Palacios\Framework\Http\HttpException;
use Palacios\Framework\Http\HttpRequest;
use Palacios\Framework\Http\HttpResponse;
use Palacios\Framework\Logging\Logger;
use Palacios\Framework\Middleware\RequestLoggingMiddleware;
use Palacios\Framework\Results\Results;
use Palacios\Framework\WebApplication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestLoggingMiddlewareTest extends TestCase
{
    public function testPublicApiUsesDefaultLoggerWithoutManualRegistration(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'palacios-http-log-');
        self::assertNotFalse($logFile);
        $previousErrorLog = ini_get('error_log');
        self::assertNotFalse($previousErrorLog);
        ini_set('error_log', $logFile);

        try {
            $app = App::createBuilder()->build();
            $app->useRequestLogging();
            $app->mapGet('/default-logger', static fn () => Results::ok());

            $response = $app->handle(new HttpRequest('GET', '/default-logger'));

            self::assertSame(200, $response->statusCode());
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            self::assertCount(1, $lines);
            $jsonStart = strpos($lines[0], '{');
            self::assertNotFalse($jsonStart);
            $record = json_decode(substr($lines[0], $jsonStart), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($record);
            self::assertSame('INFORMATION', $record['level']);
            self::assertSame('HTTP', $record['category']);
            self::assertSame('HTTP request completed', $record['message']);
            $context = $record['context'];
            self::assertIsArray($context);
            self::assertSame('GET', $context['method']);
            self::assertSame('/default-logger', $context['path']);
            self::assertSame(200, $context['statusCode']);
            $traceId = $context['traceId'];
            self::assertIsString($traceId);
            self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $traceId);
            self::assertIsFloat($context['elapsedMs']);
            self::assertGreaterThanOrEqual(0.0, $context['elapsedMs']);
            self::assertSame(
                ['traceId', 'method', 'path', 'statusCode', 'elapsedMs'],
                array_keys($context),
            );
        } finally {
            ini_set('error_log', $previousErrorLog);
            unlink($logFile);
        }
    }

    public function testApplicationLoggerRegistrationOverridesDefaultAndReceivesOneRecord(): void
    {
        [$response, $logger] = $this->execute(
            new HttpRequest('GET', '/ok'),
            static fn (WebApplication $app) => $app->mapGet('/ok', static fn () => Results::ok()),
        );

        $this->assertSingleCompletedRequest($logger, $response, 'GET', '/ok', 200);
    }

    public function testLogsRedirectWithFinalStatusOnce(): void
    {
        [$response, $logger] = $this->execute(
            new HttpRequest('GET', '/old'),
            static fn (WebApplication $app) => $app->mapGet('/old', static fn () => Results::redirect('/new')),
            usePublicApi: false,
        );

        $this->assertSingleCompletedRequest($logger, $response, 'GET', '/old', 302);
        self::assertSame('/new', $response->headers()['Location'] ?? null);
    }

    public function testLogsHttpExceptionWithFinalStatusOnce(): void
    {
        [$response, $logger] = $this->execute(
            new HttpRequest('GET', '/verification-only'),
            static fn (WebApplication $app) => $app->mapGet(
                '/verification-only',
                static function (): never {
                    throw new HttpException(419, 'Test exception');
                },
            ),
        );

        $this->assertSingleCompletedRequest($logger, $response, 'GET', '/verification-only', 419);
    }

    /** @param array<string, string> $form */
    #[DataProvider('invalidAntiforgeryForms')]
    public function testLogsRealAntiforgeryFailureWithFinalStatusOnce(array $form): void
    {
        Csrf::token();

        [$response, $logger] = $this->execute(
            new HttpRequest('POST', '/protected', form: $form),
            static function (WebApplication $app): void {
                $app->useAntiforgery();
                $app->mapPost('/protected', static fn () => Results::ok());
            },
        );

        $this->assertSingleCompletedRequest($logger, $response, 'POST', '/protected', 419);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidAntiforgeryForms(): iterable
    {
        yield 'missing token' => [[]];
        yield 'invalid token' => [['_csrf' => 'invalid']];
    }

    public function testLogsUnexpectedExceptionAsFinal500Once(): void
    {
        [$response, $logger] = $this->execute(
            new HttpRequest('GET', '/failure'),
            static fn (WebApplication $app) => $app->mapGet(
                '/failure',
                static function (): never {
                    throw new RuntimeException('Unexpected failure');
                },
            ),
        );

        $this->assertSingleCompletedRequest($logger, $response, 'GET', '/failure', 500);
    }

    public function testLogsStatusProducedByExceptionFilterOnce(): void
    {
        [$response, $logger] = $this->execute(
            new HttpRequest('GET', '/filtered-failure'),
            static function (WebApplication $app): void {
                $app->mapGet(
                    '/filtered-failure',
                    static function (): never {
                        throw new RuntimeException('Handled failure');
                    },
                )->withMetadata(new RequestLoggingExceptionFilter());
            },
        );

        $this->assertSingleCompletedRequest($logger, $response, 'GET', '/filtered-failure', 422);
    }

    public function testLoggerFailureIsConvertedToInternalServerError(): void
    {
        $app = $this->applicationWithLogger(new RequestLoggingThrowingLogger());
        $app->mapGet('/logger-failure', static fn () => Results::ok());

        $response = $app->handle(new HttpRequest('GET', '/logger-failure'));

        self::assertSame(500, $response->statusCode());
    }

    public function testLoggerFailureStillUsesEndpointExceptionFilter(): void
    {
        $app = $this->applicationWithLogger(new RequestLoggingThrowingLogger());
        $app->mapGet('/filtered-logger-failure', static fn () => Results::ok())
            ->withMetadata(new RequestLoggingExceptionFilter());

        $response = $app->handle(new HttpRequest('GET', '/filtered-logger-failure'));

        self::assertSame(422, $response->statusCode());
    }

    /**
     * @param callable(WebApplication): mixed $configure
     * @return array{HttpResponse, RequestLoggingMemoryLogger}
     */
    private function execute(HttpRequest $request, callable $configure, bool $usePublicApi = true): array
    {
        $logger = new RequestLoggingMemoryLogger();
        $app = $this->applicationWithLogger($logger, $usePublicApi);
        if (!$usePublicApi) {
            $app->use(new RequestLoggingMiddleware($logger));
        }
        $configure($app);

        return [$app->handle($request), $logger];
    }

    private function assertSingleCompletedRequest(
        RequestLoggingMemoryLogger $logger,
        HttpResponse $response,
        string $method,
        string $path,
        int $expectedStatus,
    ): void {
        self::assertSame($expectedStatus, $response->statusCode());
        self::assertCount(1, $logger->records, 'A requisição deve gerar exatamente um registro de conclusão.');

        $record = $logger->records[0];
        self::assertSame('information', $record['level']);
        self::assertSame('HTTP request completed', $record['message']);
        self::assertSame($method, $record['context']['method']);
        self::assertSame($path, $record['context']['path']);
        self::assertSame($response->statusCode(), $record['context']['statusCode']);
        $traceId = $record['context']['traceId'];
        self::assertIsString($traceId);
        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $traceId);
        self::assertIsFloat($record['context']['elapsedMs']);
        self::assertGreaterThanOrEqual(0.0, $record['context']['elapsedMs']);
        self::assertSame(
            ['traceId', 'method', 'path', 'statusCode', 'elapsedMs'],
            array_keys($record['context']),
        );
    }

    private function applicationWithLogger(Logger $logger, bool $useRequestLogging = true): WebApplication
    {
        $builder = App::createBuilder();
        $builder->services()->addSingleton(Logger::class, static fn (): Logger => $logger);
        $app = $builder->build();
        if ($useRequestLogging) {
            $app->useRequestLogging();
        }

        return $app;
    }
}

final class RequestLoggingMemoryLogger implements Logger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log(string $level, string $message, array $context = []): void
    {
        $this->records[] = compact('level', 'message', 'context');
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('information', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
}

final class RequestLoggingExceptionFilter implements IExceptionFilter
{
    public function onException(ExceptionContext $context): void
    {
        $context->result = Results::json(['handled' => true], 422);
        $context->handled = true;
    }
}

final class RequestLoggingThrowingLogger implements Logger
{
    public function log(string $level, string $message, array $context = []): void
    {
        throw new RuntimeException('Logger failure');
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('information', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
}
