<?php
declare(strict_types=1);
namespace App\Core\Validation;
final class ModelState
{
    /** @var array<string, list<string>> */
    private array $errors = [];
    public function addError(string $field, string $message): void { $this->errors[$field][] = $message; }
    public function isValid(): bool { return $this->errors === []; }
    /** @return array<string, list<string>> */
    public function errors(): array { return $this->errors; }
}
