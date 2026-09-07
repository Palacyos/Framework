<?php
declare(strict_types=1);
namespace Palacios\Framework\Routing\Attributes;
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class HttpPut extends HttpMethodAttribute { public function __construct(string $path = '', ?string $name = null) { parent::__construct('PUT', $path, $name); } }
