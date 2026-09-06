<?php
declare(strict_types=1);
namespace App\Core\Routing\Attributes;
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class HttpGet extends HttpMethodAttribute { public function __construct(string $path = '', ?string $name = null) { parent::__construct('GET', $path, $name); } }
