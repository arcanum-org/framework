<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Row casting helper.
 *
 * Produces a pure row-mapping closure from a column → type map, suitable for
 * composing onto a {@see \Arcanum\Flow\Sequence\Sequencer} via `->map()`.
 * Unmapped columns pass through untouched; empty cast maps yield an identity
 * closure. Unknown type names also pass through untouched.
 *
 * Supported types: `int`, `float`, `bool`, `json`. `bool` accepts the SQL
 * truthy strings `t`, `1`, `true`, `yes`; everything else is false.
 */
final class Cast
{
    /**
     * @param array<string, string> $casts Column → type map from {@see Sql::parseCasts()}.
     * @return \Closure(array<string, mixed>): array<string, mixed>
     */
    public static function apply(array $casts): \Closure
    {
        if ($casts === []) {
            return static fn(array $row): array => $row;
        }

        return static function (array $row) use ($casts): array {
            foreach ($casts as $column => $type) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = self::value($row[$column], $type);
                }
            }
            return $row;
        };
    }

    /**
     * Cast a single value to the given type.
     *
     * Null values pass through. Non-scalar values pass through (already
     * decoded by an earlier driver, or a complex value the cast doesn't
     * understand). Unknown type names pass through unchanged.
     */
    public static function value(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return $value;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => self::bool($value),
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }

    private static function bool(bool|int|float|string $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match ((string) $value) {
            't', '1', 'true', 'yes' => true,
            default => false,
        };
    }
}
