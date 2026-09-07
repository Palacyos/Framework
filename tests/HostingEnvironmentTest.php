<?php

declare(strict_types=1);

use Palacios\Framework\App;
use Palacios\Framework\Hosting\ApplicationEnvironment;
use Palacios\Framework\Http\HttpRequest;
use PHPUnit\Framework\TestCase;

final class HostingEnvironmentTest extends TestCase
{
    public function testEnvironmentNamesAreNormalized(): void
    {
        self::assertTrue((new ApplicationEnvironment('local'))->isDevelopment());
        self::assertTrue((new ApplicationEnvironment('test'))->isTesting());
        self::assertTrue((new ApplicationEnvironment('anything'))->isProduction());
    }

    public function testProductionProblemDetailsHideExceptionMessage(): void
    {
        $app = App::createBuilder(environmentName: 'Production')->build();
        $app->useExceptionHandler();
        $app->mapGet('/failure', static fn () => throw new RuntimeException('segredo interno'));

        $response = $app->handle(new HttpRequest('GET', '/failure'));
        $problem = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(500, $response->statusCode());
        self::assertArrayNotHasKey('detail', $problem);
    }

    public function testDevelopmentPageIncludesExceptionMessage(): void
    {
        $builder = App::createBuilder(environmentName: 'Development');
        self::assertTrue($builder->environment->isDevelopment());

        $app = $builder->build();
        $app->useDevelopmentExceptionPage();
        $app->mapGet('/failure', static fn () => throw new RuntimeException('erro visível'));

        $response = $app->handle(new HttpRequest('GET', '/failure'));
        $problem = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('erro visível', $problem['detail']);
    }

    public function testDevelopmentPageCannotBeEnabledInProduction(): void
    {
        $app = App::createBuilder(environmentName: 'Production')->build();

        $this->expectException(LogicException::class);
        $app->useDevelopmentExceptionPage();
    }
}
