<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Validates that a string does not exceed a given number of characters.
 *
 * Non-string values are skipped — type enforcement is PHP's job.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MaxLength implements Rule
{
    public function __construct(
        public readonly int $max,
    ) {
    }

    public function validate(mixed $value, string $field): ValidationError|null
    {
        if (!is_string($value)) {
            return null;
        }

        if (mb_strlen($value) > $this->max) {
            return new ValidationError(
                $field,
                "The {$field} field must not exceed {$this->max} characters.",
            );
        }

        return null;
    }
}
