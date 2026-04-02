<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Helpers;

use Arcanum\Toolkit\Strings;

/**
 * Template helper for string operations.
 *
 * Delegates to Toolkit\Strings — this is the template-facing surface.
 *
 * Usage in templates:
 *   {{ Str::truncate($text, 100) }}
 *   {{ Str::lower($name) }}
 *   {{ Str::upper($code) }}
 *   {{ Str::title($heading) }}
 *   {{ Str::kebab($slug) }}
 */
final class StrHelper
{
    public function truncate(string $text, int $length, string $suffix = '...'): string
    {
        return Strings::truncate($text, $length, $suffix);
    }

    public function lower(string $string): string
    {
        return Strings::lower($string);
    }

    public function upper(string $string): string
    {
        return Strings::upper($string);
    }

    public function title(string $string): string
    {
        return Strings::title($string);
    }

    public function kebab(string $string): string
    {
        return Strings::kebab($string);
    }
}
