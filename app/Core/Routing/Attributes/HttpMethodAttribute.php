<?php
declare(strict_types=1);
namespace App\Core\Routing\Attributes;
abstract readonly class HttpMethodAttribute
{
    public function __construct(public string $method, public string $path = '', public ?string $name = null) {}
}
