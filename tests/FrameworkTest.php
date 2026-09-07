<?php
declare(strict_types=1);

use Palacios\Framework\Health\HealthCheckResult;
use Palacios\Framework\Health\HealthCheckService;
use Palacios\Framework\Results\Results;
use Palacios\Framework\Testing\WebApplicationFactory;
use Palacios\Framework\WebApplication;
use PHPUnit\Framework\TestCase;

final class FrameworkTest extends TestCase
{
    public function testInMemoryHttpRequestReturnsJson(): void
    {
        $factory = new WebApplicationFactory(static function (WebApplication $application): void {
            $application->mapGet('/ping', static fn () => Results::json(['status' => 'ok']));
        });

        $response = $factory->createClient()->get('/ping');

        self::assertSame(200, $response->statusCode());
        self::assertSame(['status' => 'ok'], json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame('application/json; charset=utf-8', $response->headers()['Content-Type']);
    }

    public function testNotFoundIsReturnedWithoutStartingNativeResponse(): void
    {
        $response = (new WebApplicationFactory())->createClient()->get('/missing');

        self::assertSame(404, $response->statusCode());
        self::assertSame('404 Not Found', $response->body());
    }

    public function testHealthChecksAggregateStatus(): void
    {
        $health = new HealthCheckService([
            'self' => static fn () => HealthCheckResult::healthy('running'),
        ]);

        $report = $health->check();

        self::assertSame('Healthy', $report['status']);
        self::assertSame('Healthy', $report['checks']['self']['status']);
    }
}
