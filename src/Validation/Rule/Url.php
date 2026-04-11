<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Validates that a value is an HTTP or HTTPS URL.
 *
 * Rejects other schemes (ftp://, file://, etc.) since most use cases
 * expect web URLs. Use #[AnyUrl] for broader scheme support.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Url implements Rule
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

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $scheme = is_string($scheme) ? strtolower($scheme) : '';
        if ($scheme !== 'http' && $scheme !== 'https') {
            return new ValidationError(
                $field,
                "The {$field} field must use http or https.",
            );
        }

        return null;
    }
}
