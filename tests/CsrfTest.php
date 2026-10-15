<?php

declare(strict_types=1);

namespace Palacios\Framework {
    function random_bytes(int $length): string
    {
        return \CsrfTestEntropy::generate($length);
    }
}

namespace {
    use Palacios\Framework\App;
    use Palacios\Framework\Csrf;
    use Palacios\Framework\Http\HttpRequest;
    use Palacios\Framework\Logging\Logger;
    use Palacios\Framework\Results\Results;
    use Palacios\Framework\Security\Antiforgery\CsrfTokenGenerationException;
    use Palacios\Framework\Session;
    use Palacios\Framework\WebApplication;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;
    use Random\RandomException;

    final class CsrfTest extends TestCase
    {
        protected function setUp(): void
        {
            CsrfTestEntropy::reset();
            Session::remove('_csrf');
        }

        protected function tearDown(): void
        {
            Session::remove('_csrf');
            CsrfTestEntropy::reset();
        }

        public function testTokenGeneratesSixtyFourHexadecimalCharactersAndStoresThemInSession(): void
        {
            $requestedLength = null;
            CsrfTestEntropy::$generator = static function (int $length) use (&$requestedLength): string {
                $requestedLength = $length;
                return str_repeat("\xA5", $length);
            };

            $token = Csrf::token();

            self::assertSame(32, $requestedLength);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
            self::assertSame(str_repeat('a5', 32), $token);
            self::assertSame($token, Session::get('_csrf'));
        }

        public function testTokenReusesSessionValueWithoutRequestingNewEntropy(): void
        {
            $calls = 0;
            CsrfTestEntropy::$generator = static function (int $length) use (&$calls): string {
                $calls++;
                return str_repeat("\xBC", $length);
            };

            $first = Csrf::token();
            $second = Csrf::token();

            self::assertSame($first, $second);
            self::assertSame(1, $calls);
        }

        public function testRandomFailureBecomesFrameworkExceptionWithPreviousAndStoresNothing(): void
        {
            $previous = new RandomException('entropy failure sentinel');
            CsrfTestEntropy::$generator = static fn (int $length): string => throw $previous;

            try {
                Csrf::token();
                self::fail('A falha da fonte de entropia deveria interromper a geração do token.');
            } catch (CsrfTokenGenerationException $exception) {
                self::assertSame($previous, $exception->getPrevious());
                self::assertNotSame('', $exception->getMessage());
            }

            self::assertNull(Session::get('_csrf'));
        }

        public function testCsrfInputWithoutArgumentGeneratesAndEscapesSessionToken(): void
        {
            CsrfTestEntropy::$generator = static fn (int $length): string => str_repeat("\xD3", $length);

            $html = csrf_input();
            $token = Session::get('_csrf');

            self::assertIsString($token);
            self::assertSame(
                '<input type="hidden" name="_csrf" value="' . $token . '">',
                $html,
            );
        }

        public function testCsrfInputEscapesTokenPassedDuringMigration(): void
        {
            $html = csrf_input('token" onfocus="alert(1)');

            self::assertSame(
                '<input type="hidden" name="_csrf" value="token&quot; onfocus=&quot;alert(1)">',
                $html,
            );
            self::assertNull(Session::get('_csrf'));
        }

        public function testCsrfInputRejectsAnExplicitEmptyToken(): void
        {
            $this->expectException(InvalidArgumentException::class);

            csrf_input('');
        }

        public function testGenerationFailureReachesGlobalHandlerAndProducesOneSanitizedLog(): void
        {
            $previous = new RandomException('entropy failure sentinel');
            CsrfTestEntropy::$generator = static fn (int $length): string => throw $previous;
            $logger = new CsrfMemoryLogger();
            $app = $this->applicationWithLogger($logger);
            $app->useRequestLogging();
            $app->mapGet('/csrf-failure', static fn () => Results::ok(csrf_input()));

            $response = $app->handle(new HttpRequest('GET', '/csrf-failure'));

            self::assertSame(500, $response->statusCode());
            self::assertCount(1, $logger->records);
            self::assertSame(500, $logger->records[0]['context']['statusCode'] ?? null);
            $serializedLog = json_encode($logger->records, JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('_csrf', $serializedLog);
            self::assertStringNotContainsString('entropy failure sentinel', $serializedLog);
            self::assertNull(Session::get('_csrf'));
        }

        public function testValidProtectedPostDoesNotExposeTokenInRequestLog(): void
        {
            CsrfTestEntropy::$generator = static fn (int $length): string => str_repeat("\xE4", $length);
            $token = Csrf::token();
            $logger = new CsrfMemoryLogger();
            $app = $this->applicationWithLogger($logger);
            $app->useAntiforgery();
            $app->useRequestLogging();
            $app->mapPost('/protected', static fn () => Results::ok());

            $response = $app->handle(new HttpRequest('POST', '/protected', form: ['_csrf' => $token]));

            self::assertSame(200, $response->statusCode());
            self::assertCount(1, $logger->records);
            self::assertSame(200, $logger->records[0]['context']['statusCode'] ?? null);
            $serializedLog = json_encode($logger->records, JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('_csrf', $serializedLog);
            self::assertStringNotContainsString($token, $serializedLog);
        }

        #[DataProvider('invalidTokenProvider')]
        public function testMissingOrInvalidTokenStillReturns419(array $form): void
        {
            CsrfTestEntropy::$generator = static fn (int $length): string => str_repeat("\xF5", $length);
            Csrf::token();
            $app = App::createBuilder()->build();
            $app->useAntiforgery();
            $app->mapPost('/protected', static fn () => Results::ok());

            $response = $app->handle(new HttpRequest('POST', '/protected', form: $form));

            self::assertSame(419, $response->statusCode());
        }

        /** @return iterable<string, array{array<string, string>}> */
        public static function invalidTokenProvider(): iterable
        {
            yield 'missing token' => [[]];
            yield 'invalid token' => [['_csrf' => 'invalid']];
        }

        private function applicationWithLogger(Logger $logger): WebApplication
        {
            $builder = App::createBuilder();
            $builder->services()->addSingleton(Logger::class, static fn (): Logger => $logger);

            return $builder->build();
        }
    }

    final class CsrfTestEntropy
    {
        /** @var (\Closure(int): string)|null */
        public static ?\Closure $generator = null;

        public static function generate(int $length): string
        {
            return self::$generator === null
                ? \random_bytes($length)
                : (self::$generator)($length);
        }

        public static function reset(): void
        {
            self::$generator = null;
        }
    }

    final class CsrfMemoryLogger implements Logger
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
}
