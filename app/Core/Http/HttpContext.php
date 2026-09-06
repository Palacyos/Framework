<?php
declare(strict_types=1);
namespace App\Core\Http;

use App\Core\Container;
use App\Core\Validation\ModelState;
use App\Core\Security\Authentication\ClaimsPrincipal;

final class HttpContext
{
    /** @var array<string, mixed> */
    public array $items = [];
    /** @var array<string, string> */
    public array $routeValues = [];
    /** @var array{method: string, path: string, handler: mixed, middleware: array<int, string|object>, name: ?string, metadata: \App\Core\Routing\EndpointMetadataCollection}|null */
    public ?array $endpoint = null;
    public readonly ModelState $modelState;
    public ClaimsPrincipal $user;

    public function __construct(
        public readonly HttpRequest $request,
        public readonly HttpResponse $response,
        public readonly Container $services,
        public readonly string $traceId,
    ) {
        $this->modelState = new ModelState();
        $this->user = ClaimsPrincipal::anonymous();
    }

    public static function create(HttpRequest $request, Container $services): self
    {
        return new self($request, new HttpResponse(), $services, bin2hex(random_bytes(8)));
    }
}
