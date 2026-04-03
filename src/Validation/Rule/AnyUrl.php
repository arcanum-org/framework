<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Validates URL format via `filter_var(FILTER_VALIDATE_URL)`.
 *
 * Accepts any scheme (http, https, ftp, etc.). Use #[Url] instead
 * if you only want http/https — which is the safer default.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class AnyUrl implements Rule
{
    public function validate(mixed $value, string $field): ValidationError|null
    {
        if (!is_string($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return new ValidationError(
                $field,
                "The {$field} field must be a valid URL.",
            );
        }

        return null;
    }
}
