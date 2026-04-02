<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Helpers;

/**
 * Template helper for string operations.
 *
 * Usage in templates:
 *   {{ Str::truncate($text, 100) }}
 */
final class StrHelper
{
    public function truncate(string $text, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }
}
