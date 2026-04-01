<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Validates that a value is one of a fixed set of allowed values.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class In implements Rule
{
    /** @var list<mixed> */
    public readonly array $values;

    public function __construct(mixed ...$values)
    {
        $this->values = array_values($values);
    }

    public function validate(mixed $value, string $field): ValidationError|null
    {
        if (!in_array($value, $this->values, true)) {
            $list = implode(', ', array_map(
                fn(mixed $v): string => is_scalar($v) ? (string) $v : gettype($v),
                $this->values,
            ));

            return new ValidationError(
                $field,
                "The {$field} field must be one of: {$list}.",
            );
        }

        return null;
    }
}
