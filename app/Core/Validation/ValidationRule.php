<?php
declare(strict_types=1);
namespace App\Core\Validation;
interface ValidationRule { public function validate(mixed $value, string $field): ?string; }
