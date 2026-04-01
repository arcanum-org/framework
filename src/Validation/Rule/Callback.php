<?php

declare(strict_types=1);

namespace Arcanum\Validation\Rule;

use Arcanum\Validation\Rule;
use Arcanum\Validation\ValidationError;

/**
 * Escape-hatch rule for one-off validation logic.
 *
 * The callable receives the value and returns `true` if valid,
 * or a string error message if invalid.
 *
 * Usage:
 * ```php
 * #[Callback([self::class, 'validateCode'])]
 * public readonly string $code,
 * ```
 *
 * Note: PHP attributes require compile-time constant expressions,
 * so closures cannot be used directly. Use `[ClassName::class, 'method']`
 * or a class with `__invoke`.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Callback implements Rule
{
    /** @var callable(mixed): (true|string) */
    private $callback;

    /**
     * @param callable(mixed): (true|string) $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function validate(mixed $value, string $field): ValidationError|null
    {
        $result = ($this->callback)($value);

        if ($result === true) {
            return null;
        }

        return new ValidationError($field, $result);
    }
}
