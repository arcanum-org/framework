<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Row casting helper.
 *
 * Produces a pure row-mapping closure from a column → type map, suitable for
 * composing onto a {@see \Arcanum\Flow\Sequence\Sequencer} via `->map()`.
 * Unmapped columns pass through untouched; empty cast maps yield an identity
 * closure.
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
                    $row[$column] = Sql::castValue($row[$column], $type);
                }
            }
            return $row;
        };
    }
}
