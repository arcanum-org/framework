<?php

declare(strict_types=1);

namespace Arcanum\Forge;

use Arcanum\Toolkit\Strings;

/**
 * SQL string introspection utilities.
 *
 * SQL is mostly treated as strings in PHP — this class provides static
 * helpers for inspecting, classifying, and extracting information from
 * raw SQL without executing it.
 */
final class Sql
{
    /**
     * Read operations — the first meaningful keyword indicates a SELECT-type query.
     *
     * @var list<string>
     */
    private const array READ_KEYWORDS = [
        'SELECT',
        'WITH',
        'EXPLAIN',
        'SHOW',
        'DESCRIBE',
        'DESC',
        'PRAGMA',
    ];

    /**
     * Determine whether a SQL string is a read query.
     *
     * Strips leading comments (both `-- line` and block comments) and
     * checks the first keyword. SELECT, WITH (CTEs), EXPLAIN, SHOW,
     * DESCRIBE, and PRAGMA are reads. Everything else is a write.
     */
    public static function isRead(string $sql): bool
    {
        $keyword = self::firstKeyword($sql);

        if ($keyword === '') {
            return false;
        }

        return in_array(strtoupper($keyword), self::READ_KEYWORDS, true);
    }

    /**
     * Extract the first meaningful SQL keyword, skipping comments and whitespace.
     *
     * Returns the keyword in its original casing, or empty string if the
     * SQL contains only comments/whitespace.
     */
    public static function firstKeyword(string $sql): string
    {
        $pos = 0;
        $len = strlen($sql);

        while ($pos < $len) {
            // Skip whitespace.
            if (ctype_space($sql[$pos])) {
                $pos++;
                continue;
            }

            // Skip line comments: -- ...
            if ($pos + 1 < $len && $sql[$pos] === '-' && $sql[$pos + 1] === '-') {
                $newline = strpos($sql, "\n", $pos);
                $pos = $newline === false ? $len : $newline + 1;
                continue;
            }

            // Skip block comments: /* ... */
            if ($pos + 1 < $len && $sql[$pos] === '/' && $sql[$pos + 1] === '*') {
                $end = strpos($sql, '*/', $pos + 2);
                $pos = $end === false ? $len : $end + 2;
                continue;
            }

            // Skip leading parentheses: (SELECT ...)
            if ($sql[$pos] === '(') {
                $pos++;
                continue;
            }

            // Found the start of a keyword — read until non-alpha.
            $start = $pos;
            while ($pos < $len && ctype_alpha($sql[$pos])) {
                $pos++;
            }

            return substr($sql, $start, $pos - $start);
        }

        return '';
    }

    /**
     * Parse `-- @cast column type` annotations from a SQL string.
     *
     * Returns a map of column name → cast type. Supported types: int, float, bool, json.
     * Only `-- @cast` line comments are recognized — block comments are not scanned.
     *
     * @return array<string, string>
     */
    public static function parseCasts(string $sql): array
    {
        $casts = [];

        foreach (explode("\n", $sql) as $line) {
            $trimmed = ltrim($line);

            if (!str_starts_with($trimmed, '-- @cast ')) {
                continue;
            }

            $parts = preg_split('/\s+/', substr($trimmed, 9), 3);

            if ($parts === false || count($parts) < 2) {
                continue;
            }

            $casts[$parts[0]] = $parts[1];
        }

        return $casts;
    }

    /**
     * Apply cast annotations to a set of result rows.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $casts Column → type map from parseCasts().
     * @return list<array<string, mixed>>
     */
    public static function applyCasts(array $rows, array $casts): array
    {
        if ($casts === []) {
            return $rows;
        }

        foreach ($rows as $i => $row) {
            foreach ($casts as $column => $type) {
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                $rows[$i][$column] = self::castValue($row[$column], $type);
            }
        }

        return $rows;
    }

    /**
     * Extract `:named` binding names from SQL in order of first appearance.
     *
     * Scans for `:word` patterns, skipping occurrences inside line comments,
     * block comments, and string literals (single-quoted).
     *
     * @return list<string> Binding names without the leading colon.
     */
    public static function extractBindings(string $sql): array
    {
        $bindings = [];
        $seen = [];
        $pos = 0;
        $len = strlen($sql);

        while ($pos < $len) {
            $char = $sql[$pos];

            // Skip line comments.
            if ($char === '-' && $pos + 1 < $len && $sql[$pos + 1] === '-') {
                $newline = strpos($sql, "\n", $pos);
                $pos = $newline === false ? $len : $newline + 1;
                continue;
            }

            // Skip block comments.
            if ($char === '/' && $pos + 1 < $len && $sql[$pos + 1] === '*') {
                $end = strpos($sql, '*/', $pos + 2);
                $pos = $end === false ? $len : $end + 2;
                continue;
            }

            // Skip single-quoted string literals.
            if ($char === '\'') {
                $pos++;
                while ($pos < $len) {
                    if ($sql[$pos] === '\'') {
                        if ($pos + 1 < $len && $sql[$pos + 1] === '\'') {
                            $pos += 2; // escaped quote
                            continue;
                        }
                        break;
                    }
                    $pos++;
                }
                $pos++;
                continue;
            }

            // Match :named binding.
            if ($char === ':' && $pos + 1 < $len && ctype_alpha($sql[$pos + 1])) {
                $start = $pos + 1;
                $pos = $start;
                while ($pos < $len && (ctype_alnum($sql[$pos]) || $sql[$pos] === '_')) {
                    $pos++;
                }
                $name = substr($sql, $start, $pos - $start);
                if (!isset($seen[$name])) {
                    $bindings[] = $name;
                    $seen[$name] = true;
                }
                continue;
            }

            $pos++;
        }

        return $bindings;
    }

    /**
     * Parse `-- @param name type` annotations from a SQL string.
     *
     * Returns a map of parameter name → type. Supported types: string, int, float, bool.
     * Works alongside `@cast` — `@param` types what goes in, `@cast` types what comes out.
     *
     * @return array<string, string>
     */
    public static function parseParams(string $sql): array
    {
        $params = [];

        foreach (explode("\n", $sql) as $line) {
            $trimmed = ltrim($line);

            if (!str_starts_with($trimmed, '-- @param ')) {
                continue;
            }

            $parts = preg_split('/\s+/', substr($trimmed, 10), 3);

            if ($parts === false || count($parts) < 2) {
                continue;
            }

            $params[$parts[0]] = $parts[1];
        }

        return $params;
    }

    /**
     * Map __call args (mixed positional + named) to SQL binding names.
     *
     * Named args (string keys) are matched by name with camelCase → snake_case
     * conversion. Positional args (integer keys) fill the remaining unmatched
     * bindings in order of appearance. Throws if any binding has no match.
     *
     * @param array<int|string, mixed> $args The args from __call.
     * @param list<string> $bindings Binding names from extractBindings().
     * @return array<string, mixed> Keyed by binding name (with leading colon).
     */
    public static function resolveArgs(array $args, array $bindings): array
    {
        $named = [];
        $positional = [];

        foreach ($args as $key => $value) {
            if (is_string($key)) {
                $named[Strings::snake($key)] = $value;
            } else {
                $positional[] = $value;
            }
        }

        $resolved = [];
        $remaining = [];

        // Named args claim their bindings first.
        foreach ($bindings as $binding) {
            if (array_key_exists($binding, $named)) {
                $resolved[$binding] = $named[$binding];
            } else {
                $remaining[] = $binding;
            }
        }

        // Positional args fill remaining bindings in order.
        if (count($positional) > count($remaining)) {
            throw new \RuntimeException(sprintf(
                'Too many positional arguments: got %d, but only %d '
                . 'unmatched binding(s) remain (%s).',
                count($positional),
                count($remaining),
                implode(', ', $remaining),
            ));
        }

        foreach ($positional as $i => $value) {
            if (!isset($remaining[$i])) {
                break;
            }
            $resolved[$remaining[$i]] = $value;
        }

        // Check for unmatched bindings.
        $missing = array_diff($bindings, array_keys($resolved));
        if ($missing !== []) {
            throw new \RuntimeException(sprintf(
                'Missing parameter(s) for SQL binding(s): %s',
                implode(', ', array_map(fn(string $b) => ':' . $b, $missing)),
            ));
        }

        // Return in binding order for consistency.
        $ordered = [];
        foreach ($bindings as $binding) {
            $ordered[$binding] = $resolved[$binding];
        }

        return $ordered;
    }

    public static function castValue(mixed $value, string $type): mixed
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
            'bool' => self::castBool($value),
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }

    private static function castBool(bool|int|float|string $value): bool
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
