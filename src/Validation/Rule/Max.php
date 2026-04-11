<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Validates that a numeric value does not exceed a given maximum.
 *
 * Non-numeric values are skipped.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Max implements Rule
{
    public function __construct(
        public readonly int|float $max,
    ) {
    }

    public function validate(mixed $value, string $field): ValidationError|null
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        if ($value > $this->max) {
            return new ValidationError(
                $field,
                "The {$field} field must not exceed {$this->max}.",
            );
        }

        return null;
    }
}
