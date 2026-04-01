<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Validates UUID format (any version: v1–v8, including nil and max).
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Uuid implements Rule
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function validate(mixed $value, string $field): ValidationError|null
    {
        if (!is_string($value)) {
            return null;
        }

        if (!preg_match(self::PATTERN, $value)) {
            return new ValidationError(
                $field,
                "The {$field} field must be a valid UUID.",
            );
        }

        return null;
    }
}
