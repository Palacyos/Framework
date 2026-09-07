<?php
declare(strict_types=1);
namespace Palacios\Framework\Validation;
interface ValidationRule { public function validate(mixed $value, string $field): ?string; }
