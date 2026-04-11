<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Validates that a numeric value is at least a given minimum.
 *
 * Non-numeric values are skipped.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Min implements Rule
{
    public function __construct(
        public readonly int|float $min,
    ) {
    }

    public function validate(mixed $value, string $field): ValidationError|null
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        if ($value < $this->min) {
            return new ValidationError(
                $field,
                "The {$field} field must be at least {$this->min}.",
            );
        }

        return null;
    }
}
