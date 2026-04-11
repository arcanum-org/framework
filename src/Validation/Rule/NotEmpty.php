<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Rejects null, empty strings, and empty arrays.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class NotEmpty implements Rule
{
    public function validate(mixed $value, string $field): ValidationError|null
    {
        if ($value === null || $value === '' || $value === []) {
            return new ValidationError($field, "The {$field} field is required.");
        }

        return null;
    }
}
