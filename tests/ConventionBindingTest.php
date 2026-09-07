<?php
declare(strict_types=1);

use Palacios\Framework\Binding\Attributes\FromBody;
use Palacios\Framework\Results\IActionResult;
use Palacios\Framework\Results\Results;
use Palacios\Framework\Testing\WebApplicationFactory;
use Palacios\Framework\Validation\Attributes\Required;
use Palacios\Framework\WebApplication;
use Palacios\Framework\WebApplicationBuilder;
use PHPUnit\Framework\TestCase;

final class ConventionBindingTest extends TestCase
{
    public function testJsonDtoAndRegisteredServiceAreInferredWithoutAttributes(): void
    {
        $factory = new ConventionFactory();

        $response = $factory->createClient()->postJson('/convention/json', ['name' => 'Ada']);

        self::assertSame(200, $response->statusCode());
        self::assertSame(
            ['name' => 'Ada', 'service' => 'service-ok'],
            json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testFormDtoIsInferredWithoutFromForm(): void
    {
        $factory = new WebApplicationFactory(static function (WebApplication $application): void {
            $application->mapPost(
                '/convention/form',
                static fn (ConventionRequest $request): IActionResult => Results::json(['name' => $request->name]),
            );
        });

        $response = $factory->createClient()->postForm('/convention/form', ['name' => 'Grace']);

        self::assertSame(200, $response->statusCode());
        self::assertSame(['name' => 'Grace'], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testExplicitFromBodyCanBindAnArray(): void
    {
        $factory = new WebApplicationFactory(static function (WebApplication $application): void {
            $application->mapPost(
                '/convention/array',
                static fn (#[FromBody] array $data): IActionResult => Results::json($data),
            );
        });

        $response = $factory->createClient()->postJson('/convention/array', ['enabled' => true]);

        self::assertSame(200, $response->statusCode());
        self::assertSame(['enabled' => true], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
    }
}
final class ConventionFactory extends WebApplicationFactory
{
    protected function configureServices(WebApplicationBuilder $builder): void
    {
        $builder->services()->addSingleton(
            ConventionServiceContract::class,
            static fn (): ConventionService => new ConventionService('service-ok'),
        );
    }

    protected function configureApplication(WebApplication $application): void
    {
        $application->mapPost(
            '/convention/json',
            static fn (ConventionRequest $request, ConventionServiceContract $service): IActionResult =>
                Results::json(['name' => $request->name, 'service' => $service->value]),
        );
    }
}

final readonly class ConventionRequest
{
    public function __construct(#[Required] public string $name) {}
}

interface ConventionServiceContract
{
    public string $value { get; }
}

final readonly class ConventionService implements ConventionServiceContract
{
    public function __construct(public string $value) {}
}
