<?php
declare(strict_types=1);
namespace App\Core\Testing;

use App\Core\Http\HttpRequest;
use App\Core\Http\HttpResponse;
use App\Core\WebApplication;

final readonly class TestClient
{
    public function __construct(private WebApplication $application) {}

    /**
     * @param array<array-key, mixed> $query
     * @param array<string, string> $headers
     */
    public function get(string $path, array $query = [], array $headers = []): HttpResponse { return $this->request('GET', $path, query: $query, headers: $headers); }
    /**
     * @param array<array-key, mixed> $form
     * @param array<string, string> $headers
     */
    public function postForm(string $path, array $form, array $headers = []): HttpResponse { return $this->request('POST', $path, form: $form, headers: $headers); }
    /**
     * @param array<array-key, mixed> $body
     * @param array<string, string> $headers
     */
    public function postJson(string $path, array $body, array $headers = []): HttpResponse
    {
        return $this->request('POST', $path, headers: ['content-type' => 'application/json', ...$headers], body: json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $form
     * @param array<string, string> $headers
     */
    public function request(string $method, string $path, array $query = [], array $form = [], array $headers = [], string $body = ''): HttpResponse
    {
        return $this->application->handle(new HttpRequest(strtoupper($method), $path, $query, $form, headers: array_change_key_case($headers, CASE_LOWER), body: $body));
    }
}
