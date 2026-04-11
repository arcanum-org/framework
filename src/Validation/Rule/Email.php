<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Validates email format via `filter_var(FILTER_VALIDATE_EMAIL)`.
 *
 * Does not check deliverability — that's the handler's concern.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Email implements Rule
{
    public function validate(mixed $value, string $field): ValidationError|null
    {
        if (!is_string($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return new ValidationError(
                $field,
                "The {$field} field must be a valid email address.",
            );
        }

        return null;
    }
}
