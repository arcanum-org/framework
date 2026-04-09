<?php

declare(strict_types=1);

namespace Arcanum\Testing;

use Arcanum\Codex\Hydrator;
use Arcanum\Validation\Rule;
use Arcanum\Validation\Rule\Callback;
use Arcanum\Validation\Rule\Email;
use Arcanum\Validation\Rule\In;
use Arcanum\Validation\Rule\Max;
use Arcanum\Validation\Rule\MaxLength;
use Arcanum\Validation\Rule\Min;
use Arcanum\Validation\Rule\MinLength;
use Arcanum\Validation\Rule\Pattern;
use Arcanum\Validation\Rule\Url;
use Arcanum\Validation\Rule\Uuid;

/**
 * Reflection-based DTO factory for tests.
 *
 * `make($class, $overrides)` produces a valid instance of `$class` by
 * synthesizing values for any constructor parameter that lacks an override
 * and lacks a default. Synthesized values respect the parameter's
 * validation attributes for the easy rules: `#[Email]`, `#[Url]`, `#[Uuid]`,
 * `#[In]`, `#[Min]`/`#[Max]`, `#[MinLength]`/`#[MaxLength]`, `#[NotEmpty]`,
 * and nullable types.
 *
 * Factory composes `Codex\Hydrator` rather than reimplementing constructor
 * reflection: the pre-pass synthesizes a `$data` array, then `Hydrator`
 * walks the constructor, applies overrides + defaults, and coerces scalars.
 * Hydrator passes object-valued data through unchanged, so pre-built nested
 * DTOs round-trip cleanly — letting Factory recurse into nested DTO
 * parameters via a recursive `make()` call.
 *
 * Two rule classes are intentionally not auto-generatable and trigger
 * `FactoryException` with a "provide an override" hint:
 *
 *  - `#[Pattern]` — arbitrary regular expressions are user-payload-dependent.
 *  - `#[Callback]` — arbitrary callables can validate anything.
 */
final class Factory
{
    public function __construct(
        private readonly Hydrator $hydrator = new Hydrator(),
    ) {
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $overrides
     * @return T
     */
    public function make(string $class, array $overrides = []): object
    {
        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new FactoryException("Factory cannot instantiate {$class} — class is not instantiable.");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            /** @var T */
            return $reflection->newInstance();
        }

        $synthesized = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $overrides)) {
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                continue;
            }

            $synthesized[$name] = $this->synthesize($param, $class);
        }

        return $this->hydrator->hydrate($class, array_merge($synthesized, $overrides));
    }

    private function synthesize(\ReflectionParameter $param, string $class): mixed
    {
        $rules = $this->collectRules($param);

        foreach ($rules as $rule) {
            if ($rule instanceof Pattern || $rule instanceof Callback) {
                throw new FactoryException(sprintf(
                    'Factory cannot synthesize $%s on %s: %s rules are not auto-generatable. ' .
                    'Provide an explicit override.',
                    $param->getName(),
                    $class,
                    $rule::class,
                ));
            }
        }

        $type = $param->getType();

        if (!$type instanceof \ReflectionNamedType) {
            if ($type === null) {
                throw new FactoryException(sprintf(
                    'Factory cannot synthesize $%s on %s: parameter has no declared type. ' .
                    'Provide an explicit override.',
                    $param->getName(),
                    $class,
                ));
            }

            throw new FactoryException(sprintf(
                'Factory cannot synthesize $%s on %s: union and intersection types are not supported. ' .
                'Provide an explicit override.',
                $param->getName(),
                $class,
            ));
        }

        $typeName = $type->getName();

        if (!$type->isBuiltin()) {
            if ($type->allowsNull() && $rules === []) {
                return null;
            }

            if (!class_exists($typeName)) {
                throw new FactoryException(sprintf(
                    'Factory cannot synthesize $%s on %s: type %s is not a known class.',
                    $param->getName(),
                    $class,
                    $typeName,
                ));
            }

            /** @var class-string $typeName */
            return $this->make($typeName);
        }

        if ($type->allowsNull() && $rules === []) {
            return null;
        }

        return match ($typeName) {
            'string' => $this->synthesizeString($rules),
            'int' => $this->synthesizeInt($rules),
            'float' => (float) $this->synthesizeInt($rules),
            'bool' => $this->synthesizeBool($rules),
            'array' => $this->synthesizeArray($rules),
            default => throw new FactoryException(sprintf(
                'Factory cannot synthesize $%s on %s: type %s is not supported. ' .
                'Provide an explicit override.',
                $param->getName(),
                $class,
                $typeName,
            )),
        };
    }

    /**
     * @return list<Rule>
     */
    private function collectRules(\ReflectionParameter $param): array
    {
        $rules = [];
        foreach ($param->getAttributes(Rule::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $rules[] = $attribute->newInstance();
        }
        /** @var list<Rule> $rules */
        return $rules;
    }

    /**
     * @param list<Rule> $rules
     */
    private function synthesizeString(array $rules): string
    {
        foreach ($rules as $rule) {
            if ($rule instanceof In && $rule->values !== []) {
                $first = $rule->values[0];
                return is_string($first) ? $first : (string) (is_scalar($first) ? $first : '');
            }
        }

        $base = 'test';
        foreach ($rules as $rule) {
            if ($rule instanceof Email) {
                $base = 'test@example.com';
                break;
            }
            if ($rule instanceof Url) {
                $base = 'https://example.com';
                break;
            }
            if ($rule instanceof Uuid) {
                $base = '00000000-0000-4000-8000-000000000000';
                break;
            }
        }

        $minLength = 0;
        $maxLength = null;
        foreach ($rules as $rule) {
            if ($rule instanceof MinLength) {
                $minLength = max($minLength, $rule->min);
            }
            if ($rule instanceof MaxLength) {
                $maxLength = $maxLength === null ? $rule->max : min($maxLength, $rule->max);
            }
        }

        if (mb_strlen($base) < $minLength) {
            $base .= str_repeat('x', $minLength - mb_strlen($base));
        }
        if ($maxLength !== null && mb_strlen($base) > $maxLength) {
            $base = mb_substr($base, 0, $maxLength);
        }

        return $base;
    }

    /**
     * @param list<Rule> $rules
     */
    private function synthesizeInt(array $rules): int
    {
        foreach ($rules as $rule) {
            if ($rule instanceof In && $rule->values !== []) {
                $first = $rule->values[0];
                return is_int($first) ? $first : (int) (is_numeric($first) ? $first : 0);
            }
        }

        $value = 1;
        foreach ($rules as $rule) {
            if ($rule instanceof Min && $value < $rule->min) {
                $value = (int) ceil($rule->min);
            }
            if ($rule instanceof Max && $value > $rule->max) {
                $value = (int) floor($rule->max);
            }
        }

        return $value;
    }

    /**
     * @param list<Rule> $rules
     */
    private function synthesizeBool(array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule instanceof In && $rule->values !== []) {
                return (bool) $rule->values[0];
            }
        }

        return true;
    }

    /**
     * @param list<Rule> $rules
     * @return list<mixed>
     */
    private function synthesizeArray(array $rules): array
    {
        foreach ($rules as $rule) {
            if ($rule instanceof In && $rule->values !== []) {
                $first = $rule->values[0];
                return is_array($first) ? array_values($first) : [$first];
            }
        }

        return ['x'];
    }
}
