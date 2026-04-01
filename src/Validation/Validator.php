<?php

declare(strict_types=1);

namespace Arcanum\Validation;

use Arcanum\Flow\Conveyor\HandlerProxy;

/**
 * Validates a DTO by inspecting Rule attributes on its constructor parameters.
 *
 * Collects all errors before throwing — the developer sees every problem at once.
 * Usable standalone (manual validation) or via the ValidationGuard middleware.
 */
final class Validator
{
    /**
     * Validate a DTO. Throws on failure.
     *
     * @throws ValidationException If one or more rules fail.
     */
    public function validate(object $dto): void
    {
        $errors = $this->check($dto);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Check a DTO and return errors without throwing.
     *
     * @return list<ValidationError>
     */
    public function check(object $dto): array
    {
        if ($dto instanceof HandlerProxy) {
            return [];
        }

        $ref = new \ReflectionClass($dto);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $errors = [];

        foreach ($constructor->getParameters() as $param) {
            $rules = $param->getAttributes(Rule::class, \ReflectionAttribute::IS_INSTANCEOF);

            if ($rules === []) {
                continue;
            }

            $name = $param->getName();
            $value = $ref->getProperty($name)->getValue($dto);

            // Nullable params with null values skip all rules.
            $type = $param->getType();
            if ($value === null && $type instanceof \ReflectionNamedType && $type->allowsNull()) {
                continue;
            }

            foreach ($rules as $attribute) {
                /** @var Rule $rule */
                $rule = $attribute->newInstance();
                $error = $rule->validate($value, $name);

                if ($error !== null) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }
}
