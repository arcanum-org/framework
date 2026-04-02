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
}
