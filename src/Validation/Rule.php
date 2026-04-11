<?php

declare(strict_types=1);

namespace Arcanum\Validation;

/**
 * A validation rule that can be applied as a PHP attribute on DTO constructor parameters.
 *
 * Every built-in and custom rule implements this interface and is decorated
 * with `#[Attribute(Attribute::TARGET_PARAMETER)]`. This lets rules serve
 * double duty: they are both the attribute the developer writes on their DTO
 * and the object that performs the validation.
 */
interface Rule
{
    /**
     * Validate a value for the given field.
     *
     * @return ValidationError|null Null if valid, or an error describing the failure.
     */
    public function validate(mixed $value, string $field): ValidationError|null;
}
