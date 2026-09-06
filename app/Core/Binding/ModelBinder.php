<?php
declare(strict_types=1);
namespace App\Core\Binding;

use App\Core\Binding\Attributes\FromBody;
use App\Core\Binding\Attributes\FromForm;
use App\Core\Binding\Attributes\FromHeader;
use App\Core\Binding\Attributes\FromQuery;
use App\Core\Binding\Attributes\FromRoute;
use App\Core\Binding\Attributes\FromServices;
use App\Core\Http\HttpContext;
use App\Core\Http\HttpException;
use App\Core\Validation\ValidationRule;
use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

final readonly class ModelBinder
{
    public function bind(ReflectionParameter $parameter, HttpContext $context): mixed
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType) {
            throw new HttpException(400, "O parâmetro '{$parameter->getName()}' deve possuir um tipo simples.");
        }

        if ($parameter->getAttributes(FromServices::class)) return $context->services->make($type->getName());

        [$source, $explicitName] = $this->source($parameter, $context);
        $name = $explicitName ?? $parameter->getName();

        if (!$type->isBuiltin()) {
            $class = $type->getName();
            if ($source === null && $context->services->has($class)) {
                return $context->services->make($class);
            }
            if (!class_exists($class)) {
                throw new HttpException(400, "Tipo de modelo inválido: {$class}.");
            }
            $source ??= $this->inferModelSource($context);
            if ($source === null) {
                return $context->services->make($class);
            }
            return $this->hydrate($class, $source, $context);
        }

        if ($type->getName() === 'array' && $parameter->getAttributes(FromBody::class)) {
            return $source ?? [];
        }

        if ($source === null) $source = $context->routeValues + $context->request->query;
        if (!array_key_exists($name, $source)) {
            if ($parameter->isDefaultValueAvailable()) return $parameter->getDefaultValue();
            if ($type->allowsNull()) return null;
            $context->modelState->addError($name, "O campo {$name} é obrigatório.");
            throw new HttpException(400, 'Um ou mais erros de validação ocorreram.', $context->modelState->errors());
        }
        return $this->convert($source[$name], $type->getName(), $name);
    }

    /** @return array{array<array-key, mixed>|null, string|null} */
    private function source(ReflectionParameter $parameter, HttpContext $context): array
    {
        foreach ([
            FromForm::class => $context->request->form,
            FromQuery::class => $context->request->query,
            FromRoute::class => $context->routeValues,
            FromHeader::class => $context->request->headers,
        ] as $attribute => $values) {
            $found = $parameter->getAttributes($attribute);
            if ($found) return [$values, $found[0]->newInstance()->name];
        }
        if ($parameter->getAttributes(FromBody::class)) {
            try { return [$context->request->json(), null]; }
            catch (\JsonException $exception) { throw new HttpException(400, 'O corpo JSON é inválido.', previous: $exception); }
        }
        return [null, null];
    }

    /** @return array<array-key, mixed>|null */
    private function inferModelSource(HttpContext $context): ?array
    {
        $contentType = strtolower($context->request->header('content-type', '') ?? '');
        if (str_contains($contentType, 'application/json') || $context->request->body !== '') {
            try {
                return $context->request->json();
            } catch (\JsonException $exception) {
                throw new HttpException(400, 'O corpo JSON é inválido.', previous: $exception);
            }
        }
        return $context->request->form !== [] ? $context->request->form : null;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed> $source
     */
    private function hydrate(string $class, array $source, HttpContext $context): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) return $reflection->newInstance();

        $arguments = [];
        foreach ($constructor->getParameters() as $field) {
            $name = $field->getName();
            $value = $source[$name] ?? null;
            foreach ($field->getAttributes() as $attribute) {
                $rule = $attribute->newInstance();
                if ($rule instanceof ValidationRule && ($message = $rule->validate($value, $name))) $context->modelState->addError($name, $message);
            }
            if ($value === null) {
                if ($field->isDefaultValueAvailable()) { $arguments[] = $field->getDefaultValue(); continue; }
                if ($field->allowsNull()) { $arguments[] = null; continue; }
                $context->modelState->addError($name, "O campo {$name} é obrigatório.");
                $arguments[] = null;
                continue;
            }
            $fieldType = $field->getType();
            $arguments[] = $fieldType instanceof ReflectionNamedType ? $this->convert($value, $fieldType->getName(), $name) : $value;
        }

        if (!$context->modelState->isValid()) throw new HttpException(400, 'Um ou mais erros de validação ocorreram.', $context->modelState->errors());
        return $reflection->newInstanceArgs($arguments);
    }

    private function convert(mixed $value, string $type, string $name): mixed
    {
        if (is_subclass_of($type, BackedEnum::class)) {
            if (!is_int($value) && !is_string($value)) {
                throw new HttpException(400, "Valor inválido para {$name}.");
            }
            return $type::tryFrom($value) ?? throw new HttpException(400, "Valor inválido para {$name}.");
        }
        return match ($type) {
            'string', 'mixed' => is_scalar($value) || $value instanceof \Stringable
                ? (string) $value
                : throw new HttpException(400, "O campo {$name} deve ser textual."),
            'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?? throw new HttpException(400, "O campo {$name} deve ser inteiro."),
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? throw new HttpException(400, "O campo {$name} deve ser numérico."),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? throw new HttpException(400, "O campo {$name} deve ser booleano."),
            'array' => is_array($value) ? $value : throw new HttpException(400, "O campo {$name} deve ser uma lista."),
            default => $value,
        };
    }
}
