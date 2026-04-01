<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Validates a value against a regular expression.
 *
 * For phone numbers, slugs, codes, and any custom format.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Pattern implements Rule
{
    public function __construct(
        public readonly string $regex,
    ) {
    }

    public function validate(mixed $value, string $field): ValidationError|null
    {
        if (!is_string($value)) {
            return null;
        }

        if (!preg_match($this->regex, $value)) {
            return new ValidationError(
                $field,
                "The {$field} field format is invalid.",
            );
        }

        return null;
    }
}
