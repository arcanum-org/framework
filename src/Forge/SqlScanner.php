<?php

declare(strict_types=1);

namespace Arcanum\Forge;

/**
 * Character-level SQL scanner that distinguishes code from non-code regions.
 *
 * Walks through a SQL string, skipping line comments (--), block comments,
 * and single-quoted string literals. Calls a visitor callback for each
 * character that is part of executable SQL (not inside a comment or string).
 *
 * Extracted from Sql::extractBindings() to reduce complexity and make the
 * skip-comments-and-strings logic reusable.
 */
final class SqlScanner
{
    /**
     * Scan SQL and call $visitor for each code character.
     *
     * The visitor receives the full SQL string and the current position.
     * It may advance the position by returning a new position; returning
     * null means "advance by one as normal."
     *
     * @param callable(string $sql, int $pos): ?int $visitor
     */
    public static function scan(string $sql, callable $visitor): void
    {
        $pos = 0;
        $len = strlen($sql);

        while ($pos < $len) {
            $char = $sql[$pos];

            // Skip line comments (-- to end of line).
            if ($char === '-' && $pos + 1 < $len && $sql[$pos + 1] === '-') {
                $newline = strpos($sql, "\n", $pos);
                $pos = $newline === false ? $len : $newline + 1;
                continue;
            }

            // Skip block comments (/* ... */).
            if ($char === '/' && $pos + 1 < $len && $sql[$pos + 1] === '*') {
                $end = strpos($sql, '*/', $pos + 2);
                $pos = $end === false ? $len : $end + 2;
                continue;
            }

            // Skip single-quoted string literals (handling escaped quotes).
            if ($char === '\'') {
                $pos++;
                while ($pos < $len) {
                    if ($sql[$pos] === '\'') {
                        if ($pos + 1 < $len && $sql[$pos + 1] === '\'') {
                            $pos += 2; // escaped quote ('')
                            continue;
                        }
                        break;
                    }
                    $pos++;
                }
                $pos++;
                continue;
            }

            // This character is executable SQL — call the visitor.
            $newPos = $visitor($sql, $pos);

            if ($newPos !== null) {
                $pos = $newPos;
            } else {
                $pos++;
            }
        }
    }
}
