<?php

declare(strict_types=1);

namespace Arcanum\Forge;

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

    private static function castValue(mixed $value, string $type): mixed
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
