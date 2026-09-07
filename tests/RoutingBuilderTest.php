<?php

declare(strict_types=1);

use Palacios\Framework\App;
use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Results\Results;
use PHPUnit\Framework\TestCase;

final class RoutingBuilderTest extends TestCase
{
    public function testFluentRouteCanBeNamedAndGenerateUrl(): void
    {
        $app = App::createBuilder(environmentName: 'Testing')->build();
        $app->mapGet('/users/{id:int}', static fn (int $id) => Results::json(['id' => $id]))
            ->withName('users.show')
            ->withTags('Users')
            ->withSummary('Obtém um usuário')
            ->produces(200, 'application/json');

        self::assertSame('/users/42', $app->urlFor('users.show', ['id' => 42]));
        self::assertSame(['id' => 42], json_decode($app->handle(
            new \Palacios\Framework\Http\HttpRequest('GET', '/users/42'),
        )->body(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testGroupAndNestedGroupInheritMiddlewareAndPrefix(): void
    {
        $app = App::createBuilder(environmentName: 'Testing')->build();
        $api = $app->mapGroup('/api')->addMiddleware(new RoutingHeaderMiddleware());
        $api->mapGroup('/v1')->mapGet('/ping', static fn () => Results::ok());

        $response = $app->handle(new \Palacios\Framework\Http\HttpRequest('GET', '/api/v1/ping'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('yes', $response->headers()['X-Group-Middleware']);
    }

    public function testGroupAuthorizationCanBeOverriddenByAllowAnonymous(): void
    {
        $builder = App::createBuilder(environmentName: 'Testing');
        $builder->services()->addAuthentication();
        $builder->services()->addAuthorization();
        $app = $builder->build();
        $app->useAuthorization();
        $group = $app->mapGroup('/admin')->requireAuthorization();
        $group->mapGet('/private', static fn () => Results::ok());
        $group->mapGet('/public', static fn () => Results::ok())->allowAnonymous();

        self::assertSame(401, $app->handle(
            new \Palacios\Framework\Http\HttpRequest('GET', '/admin/private'),
        )->statusCode());
        self::assertSame(200, $app->handle(
            new \Palacios\Framework\Http\HttpRequest('GET', '/admin/public'),
        )->statusCode());
    }

    public function testDuplicateMethodAndPathAreRejected(): void
    {
        $app = App::createBuilder(environmentName: 'Testing')->build();
        $app->mapGet('/duplicate', static fn () => Results::ok());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rota duplicada: GET /duplicate');
        $app->mapGet('/duplicate/', static fn () => Results::ok());
    }

    public function testDuplicateRouteNamesAreRejected(): void
    {
        $app = App::createBuilder(environmentName: 'Testing')->build();
        $app->mapGet('/first', static fn () => Results::ok())->withName('same');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nome de rota duplicado: same');
        $app->mapGet('/second', static fn () => Results::ok())->withName('same');
    }

    public function testInvalidGroupPrefixesAreRejected(): void
    {
        $app = App::createBuilder(environmentName: 'Testing')->build();

        $this->expectException(InvalidArgumentException::class);
        $app->mapGroup('/api?version=1');
    }
}

final class RoutingHeaderMiddleware
{
    public function handle(HttpContext $context, callable $next): void
    {
        $context->response->header('X-Group-Middleware', 'yes');
        $next();
    }
}
