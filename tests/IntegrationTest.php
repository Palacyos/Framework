<?php
declare(strict_types=1);

use App\Core\Binding\Attributes\FromBody;
use App\Core\Binding\Attributes\FromForm;
use App\Core\Binding\Attributes\FromRoute;
use App\Core\Csrf;
use App\Core\Results\IActionResult;
use App\Core\Results\Results;
use App\Core\Routing\Attributes\ApiController;
use App\Core\Routing\Attributes\HttpGet;
use App\Core\Routing\Attributes\Route;
use App\Core\Security\Attributes\AllowAnonymous;
use App\Core\Security\Attributes\Authorize;
use App\Core\Testing\WebApplicationFactory;
use App\Core\Validation\Attributes\EmailAddress;
use App\Core\Validation\Attributes\Required;
use App\Core\WebApplication;
use App\Middlewares\AuthorizationMiddleware;
use App\Middlewares\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    public function testFormBindingDoesNotExposeAntiforgeryFieldToDto(): void
    {
        $token = Csrf::token();
        $factory = new WebApplicationFactory(static function (WebApplication $application): void {
            $application->mapPost(
                '/users',
                static fn (#[FromForm] IntegrationCreateUser $model): IActionResult => Results::json([
                    'name' => $model->name,
                    'properties' => array_keys(get_object_vars($model)),
                ]),
                [new CsrfMiddleware()],
            );
        });

        $response = $factory->createClient()->postForm('/users', [
            '_csrf' => $token,
            'name' => 'Ada',
            'unexpected' => 'ignored',
        ]);

        self::assertSame(200, $response->statusCode());
        self::assertSame(
            ['name' => 'Ada', 'properties' => ['name']],
            json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testInvalidAntiforgeryTokenReturnsProblemDetails(): void
    {
        Csrf::token();
        $factory = new WebApplicationFactory(static function (WebApplication $application): void {
            $application->mapPost('/protected', static fn (): IActionResult => Results::ok(), [new CsrfMiddleware()]);
        });

        $response = $factory->createClient()->postForm('/protected', ['_csrf' => 'invalid']);
        $problem = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(419, $response->statusCode());
        self::assertSame(419, $problem['status']);
        self::assertArrayHasKey('traceId', $problem);
    }

    public function testRouteBindingConvertsIntegerConstraint(): void
    {
        $factory = new WebApplicationFactory(static function (WebApplication $application): void {
            $application->mapGet(
                '/orders/{id:int}',
                static fn (#[FromRoute] int $id): IActionResult => Results::json([
                    'id' => $id,
                    'type' => get_debug_type($id),
                ]),
            );
        });

        $response = $factory->createClient()->get('/orders/42');

        self::assertSame(200, $response->statusCode());
        self::assertSame(['id' => 42, 'type' => 'int'], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testInvalidBodyModelReturnsValidationProblem(): void
    {
        $factory = new WebApplicationFactory(static function (WebApplication $application): void {
            $application->mapPost(
                '/contacts',
                static fn (#[FromBody] IntegrationContact $model): IActionResult => Results::json($model),
            );
        });

        $response = $factory->createClient()->postJson('/contacts', ['name' => '', 'email' => 'not-an-email']);
        $problem = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->statusCode());
        self::assertArrayHasKey('name', $problem['errors']);
        self::assertArrayHasKey('email', $problem['errors']);
    }

    public function testMethodNotAllowedReturnsAllowHeader(): void
    {
        $factory = new WebApplicationFactory(static function (WebApplication $application): void {
            $application->mapGet('/only-get', static fn (): IActionResult => Results::ok());
        });

        $response = $factory->createClient()->postForm('/only-get', []);

        self::assertSame(405, $response->statusCode());
        self::assertSame('GET', $response->headers()['Allow']);
    }

    public function testDeclarativeAuthorizationReturns401AndAllowsAnonymousOverride(): void
    {
        $factory = new WebApplicationFactory(static function (WebApplication $application): void {
            $application->use(new AuthorizationMiddleware());
            $application->mapControllers(IntegrationSecureController::class);
        });
        $client = $factory->createClient();

        self::assertSame(401, $client->get('/secure/private')->statusCode());
        self::assertSame(200, $client->get('/secure/public')->statusCode());
    }
}

final readonly class IntegrationCreateUser
{
    public function __construct(#[Required] public string $name) {}
}

final readonly class IntegrationContact
{
    public function __construct(
        #[Required] public string $name,
        #[EmailAddress] public string $email,
    ) {}
}

#[ApiController]
#[Route('/secure')]
#[Authorize]
final class IntegrationSecureController
{
    #[HttpGet('/private')]
    public function privateAction(): IActionResult
    {
        return Results::ok();
    }

    #[HttpGet('/public')]
    #[AllowAnonymous]
    public function publicAction(): IActionResult
    {
        return Results::ok();
    }
}
