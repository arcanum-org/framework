<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Helpers;

/**
 * Template helper for number and date formatting.
 *
 * Usage in templates:
 *   {{ Format::number($price, 2) }}
 *   {{ Format::date($timestamp, 'M j, Y') }}
 */
final class FormatHelper
{
    public function number(
        float|int $value,
        int $decimals = 0,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ',',
    ): string {
        return number_format($value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Format a caller-provided timestamp.
     *
     * This is a pure formatter — every call site passes the timestamp it wants
     * formatted, and the function returns the rendered string. It deliberately
     * does NOT use Hourglass\Clock: there is no "now" being read here, so there
     * is no testability boundary to cross. (The strtotime() branch can interpret
     * relative strings like "+1 day" against the system clock, but choosing to
     * pass such a string is the caller's decision; the helper itself stays
     * value-in / value-out.)
     */
    public function date(
        int|string|\DateTimeInterface $timestamp,
        string $format = 'M j, Y',
    ): string {
        if ($timestamp instanceof \DateTimeInterface) {
            return $timestamp->format($format);
        }

        if (is_int($timestamp)) {
            return date($format, $timestamp);
        }

        return date($format, strtotime($timestamp) ?: 0);
    }
}
