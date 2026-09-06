<?php
declare(strict_types=1);
namespace App\Core\Validation\Attributes;
use App\Core\Validation\ValidationRule;
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class EmailAddress implements ValidationRule
{
    public function __construct(public ?string $message = null) {}
    public function validate(mixed $value, string $field): ?string { return $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) === false ? ($this->message ?? "O campo {$field} deve ser um e-mail válido.") : null; }
}
