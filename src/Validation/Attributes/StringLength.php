<?php
declare(strict_types=1);
namespace Palacios\Framework\Validation\Attributes;
use Palacios\Framework\Validation\ValidationRule;
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class StringLength implements ValidationRule
{
    public function __construct(public int $max, public int $min = 0, public ?string $message = null) {}
    public function validate(mixed $value, string $field): ?string
    {
        if ($value === null) return null;
        if (!is_string($value)) return $this->message ?? "O campo {$field} deve ser textual.";
        $length = mb_strlen($value);
        return $length < $this->min || $length > $this->max ? ($this->message ?? "O campo {$field} deve ter entre {$this->min} e {$this->max} caracteres.") : null;
    }
}
