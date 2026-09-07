<?php

declare(strict_types=1);

use Palacios\Framework\Container;
use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Http\HttpException;
use Palacios\Framework\Http\HttpRequest;
use Palacios\Framework\Security\Authentication\AuthenticationOptions;
use Palacios\Framework\Security\Authentication\DefaultAuthenticationFailureHandler;
use PHPUnit\Framework\TestCase;

final class AuthenticationFailureHandlerTest extends TestCase
{
    public function testMvcPathsConvertChallengeAndForbiddenIntoRedirects(): void
    {
        $options = (new AuthenticationOptions())
            ->loginPath('/login')
            ->accessDeniedPath('/acesso-negado');
        $handler = new DefaultAuthenticationFailureHandler($options);

        $challenge = HttpContext::create(new HttpRequest('GET', '/private'), new Container());
        $handler->challenge($challenge);

        self::assertSame(302, $challenge->response->statusCode());
        self::assertSame('/login', $challenge->response->headers()['Location']);

        $forbidden = HttpContext::create(new HttpRequest('GET', '/admin'), new Container());
        $handler->forbid($forbidden);

        self::assertSame(302, $forbidden->response->statusCode());
        self::assertSame('/acesso-negado', $forbidden->response->headers()['Location']);
    }

    public function testApiDefaultsKeepHttpStatusSemantics(): void
    {
        $handler = new DefaultAuthenticationFailureHandler();
        $context = HttpContext::create(new HttpRequest('GET', '/api/private'), new Container());

        try {
            $handler->challenge($context);
            self::fail('Challenge deveria lançar HttpException.');
        } catch (HttpException $exception) {
            self::assertSame(401, $exception->statusCode);
        }

        try {
            $handler->forbid($context);
            self::fail('Forbid deveria lançar HttpException.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->statusCode);
        }
    }
}
