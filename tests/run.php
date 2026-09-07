<?php
declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) return;
        $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) require $path;
    });
}

use Palacios\Framework\Binding\Attributes\FromForm;
use Palacios\Framework\Binding\ModelBinder;
use Palacios\Framework\Configuration\Configuration;
use Palacios\Framework\Http\HttpContext;
use Palacios\Framework\Validation\Attributes\EmailAddress;
use Palacios\Framework\Validation\Attributes\Required;
use Palacios\Framework\Routing\Attributes\HttpGet;
use Palacios\Framework\Routing\Attributes\Route;
use Palacios\Framework\WebApplication;
use Palacios\Framework\Security\Authentication\Claim;
use Palacios\Framework\Security\Authentication\ClaimsIdentity;
use Palacios\Framework\Security\Authentication\ClaimsPrincipal;
use Palacios\Framework\Security\Authorization\AuthorizationOptions;
use Palacios\Framework\Security\Authorization\AuthorizationService;
use Palacios\Framework\Filters\ActionExecutedContext;
use Palacios\Framework\Filters\ActionExecutingContext;
use Palacios\Framework\Filters\FilterPipeline;
use Palacios\Framework\Filters\IActionFilter;
use Palacios\Framework\Filters\IResultFilter;
use Palacios\Framework\Filters\ResultExecutingContext;
use Palacios\Framework\Results\ContentResult;
use Palacios\Framework\Routing\EndpointMetadataCollection;
use Palacios\Framework\Testing\WebApplicationFactory;
use Palacios\Framework\Results\Results;
use Palacios\Framework\Health\HealthCheckResult;
use Palacios\Framework\Health\HealthCheckService;
use Palacios\Framework\Container;
use Palacios\Framework\Http\HttpRequest;
use Palacios\Framework\Http\HttpResponse;
use Palacios\Framework\ServiceCollection;

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$request = new HttpRequest('GET', '/users/42', ['page' => '2'], headers: ['accept' => 'application/json']);
expect($request->isSafeMethod(), 'GET deveria ser método seguro.');
expect($request->header('Accept') === 'application/json', 'Headers devem ser case-insensitive.');

$response = new HttpResponse();
$response->status(201)->header('X-Test', 'ok')->write('created');
expect($response->statusCode() === 201, 'Status deveria ser 201.');
expect($response->body() === 'created', 'Body deveria ser acumulado.');

$config = Configuration::fromEnvironment(['APP__NAME' => 'Framework', 'APP__DEBUG' => 'true']);
expect($config->getString('app.name') === 'Framework', 'Configuração hierárquica inválida.');
expect($config->getBool('app.debug'), 'Conversão booleana inválida.');

interface Clock {}
final class SystemClock implements Clock {}
$services = new ServiceCollection();
$services->addSingleton(Clock::class, SystemClock::class);
$container = new Container($services->getBindings());
expect($container->make(Clock::class) === $container->make(Clock::class), 'Singleton deveria reutilizar instância.');

final class CircularA { public function __construct(public CircularB $b) {} }
final class CircularB { public function __construct(public CircularA $a) {} }
try {
    $container->make(CircularA::class);
    throw new RuntimeException('Dependência circular deveria falhar.');
} catch (RuntimeException $exception) {
    expect(str_contains($exception->getMessage(), 'Dependência circular'), 'Erro circular deveria ser explícito.');
}

final readonly class CreateUserRequest
{
    public function __construct(
        #[Required] public string $name,
        #[Required, EmailAddress] public string $email,
    ) {}
}
$action = static function (#[FromForm] CreateUserRequest $request): void {};
$parameter = (new ReflectionFunction($action))->getParameters()[0];
$formRequest = new HttpRequest('POST', '/users', form: [
    '_csrf' => 'infra-token',
    'name' => 'Ada',
    'email' => 'ada@example.test',
    'ignored' => 'not-bound',
]);
$formContext = HttpContext::create($formRequest, $container);
$dto = (new ModelBinder())->bind($parameter, $formContext);
expect($dto instanceof CreateUserRequest, 'FromForm deveria criar o DTO.');
expect($dto->name === 'Ada' && $dto->email === 'ada@example.test', 'DTO deveria receber apenas seus campos.');
expect(!property_exists($dto, '_csrf'), 'O token antiforgery não pode chegar ao DTO.');

#[Route('/users')]
final class AttributeController
{
    #[HttpGet('/{id:int}', name: 'users.show')]
    public function show(int $id): void {}
}
$application = new WebApplication($container);
$application->mapControllers(AttributeController::class);
expect($application->urlFor('users.show', ['id' => 42]) === '/users/42', 'Rotas nomeadas deveriam gerar URLs.');

$principal = new ClaimsPrincipal([new ClaimsIdentity([
    new Claim('sub', '42'),
    new Claim('role', 'admin'),
    new Claim('permission', 'users.write'),
], 'test')]);
expect($principal->identity()->isAuthenticated(), 'Principal deveria estar autenticado.');
expect($principal->isInRole('admin'), 'Role admin deveria existir.');
expect($principal->hasClaim('permission', 'users.write'), 'Claim de permissão deveria existir.');

$options = new AuthorizationOptions();
$options->addPolicy('users.write', static fn ($policy) => $policy
    ->requireAuthenticatedUser()
    ->requireRole('admin')
    ->requireClaim('permission', 'users.write'));
$policy = $options->getPolicy('users.write');
expect($policy !== null && (new AuthorizationService())->authorize($principal, $policy), 'Policy deveria autorizar o principal.');
expect(!(new AuthorizationService())->authorize(ClaimsPrincipal::anonymous(), $policy), 'Policy deveria negar usuário anônimo.');

final class CounterFilter implements IActionFilter, IResultFilter
{
    public array $events = [];
    public function onActionExecuting(ActionExecutingContext $context): void { $this->events[] = 'action:before'; }
    public function onActionExecuted(ActionExecutedContext $context): void { $this->events[] = 'action:after'; }
    public function onResultExecuting(ResultExecutingContext $context): void { $this->events[] = 'result:before'; }
    public function onResultExecuted(ResultExecutingContext $context): void { $this->events[] = 'result:after'; }
}
$filter = new CounterFilter();
$filterContext = HttpContext::create(new HttpRequest('GET', '/filter'), $container);
$filterContext->endpoint = ['metadata' => new EndpointMetadataCollection([$filter])];
$filterPipeline = new FilterPipeline($container);
$filterResult = $filterPipeline->invokeAction($filterContext, [], static fn () => new ContentResult('filtered'));
expect($filterResult instanceof ContentResult, 'Filtro de action deveria preservar o resultado.');
$filterPipeline->invokeResult($filterContext, $filterResult);
expect($filterContext->response->body() === 'filtered', 'Result deveria escrever no HttpResponse.');
expect($filter->events === ['action:before', 'action:after', 'result:before', 'result:after'], 'Ordem dos filtros inválida.');

$factory = new WebApplicationFactory(static function (WebApplication $app): void {
    $app->mapGet('/ping', static fn () => Results::json(['status' => 'ok']));
});
$httpResponse = $factory->createClient()->get('/ping');
expect($httpResponse->statusCode() === 200, 'Cliente HTTP deveria receber status 200.');
expect(json_decode($httpResponse->body(), true, 512, JSON_THROW_ON_ERROR) === ['status' => 'ok'], 'Cliente HTTP deveria receber JSON.');

$health = new HealthCheckService([
    'self' => static fn () => HealthCheckResult::healthy('Application is running'),
]);
$healthReport = $health->check();
expect($healthReport['status'] === 'Healthy', 'Health check deveria estar saudável.');

json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

echo "Framework smoke tests: OK\n";
