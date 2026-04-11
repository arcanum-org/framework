<?php

declare(strict_types=1);

namespace Arcanum\Codex;

/**
 * Constructs objects by matching an associative array of values to
 * constructor parameter names, with type coercion for scalar types.
 *
 * Used to hydrate DTOs from request data (query params, request body).
 * Parameters not present in the data fall back to their default values.
 * Parameters with no default and no matching data throw UnresolvableClass.
 */
final class Hydrator
{
    /**
     * Hydrate a class from an associative array of values.
     *
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $data Keyed by parameter name.
     * @return T
     */
    public function hydrate(string $className, array $data): object
    {
        $image = new \ReflectionClass($className);

        if (!$image->isInstantiable()) {
            throw new Error\UnresolvableClass(message: $className);
        }

        $constructor = $image->getConstructor();

        if ($constructor === null || count($constructor->getParameters()) === 0) {
            return $image->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $data)) {
                $arguments[] = $this->coerce($data[$name], $parameter);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                throw new Error\UnresolvableClass(
                    "$className requires parameter '\$$name', but it was not provided."
                );
            }
        }

        /** @var T */
        return $image->newInstanceArgs($arguments);
    }

    /**
     * Coerce a value to match the parameter's declared type.
     */
    private function coerce(mixed $value, \ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type === null || !$type instanceof \ReflectionNamedType || $type->isBuiltin() === false) {
            return $value;
        }

        if (!is_scalar($value) && !is_array($value) && $value !== null) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            'string' => is_scalar($value) ? (string) $value : $value,
            'array' => (array) $value,
            default => $value,
        };
    }
}
