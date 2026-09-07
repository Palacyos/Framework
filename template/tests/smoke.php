<?php

declare(strict_types=1);

use Palacios\Framework\Http\HttpRequest;
use Palacios\Framework\WebApplication;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';

if (!$app instanceof WebApplication) {
    throw new RuntimeException('O bootstrap não retornou uma WebApplication.');
}

$reflection = new ReflectionClass(WebApplication::class);
$frameworkFile = $reflection->getFileName();
$vendorPath = realpath(dirname(__DIR__) . '/vendor/palacios/framework');

if ($frameworkFile === false || $vendorPath === false || !str_starts_with($frameworkFile, $vendorPath)) {
    throw new RuntimeException('O framework não foi carregado pelo vendor do template.');
}

$response = $app->handle(new HttpRequest('GET', '/'));

if ($response->statusCode() !== 302 || $response->headers()['Location'] !== '/home') {
    throw new RuntimeException('O pipeline HTTP do template não retornou o redirect esperado.');
}

echo "Template vendor smoke test: OK\n";
