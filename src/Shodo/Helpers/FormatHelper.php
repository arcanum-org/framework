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
     * Pure value-in / value-out — never reads wall-clock "now," so it does
     * not cross a Hourglass\Clock testability boundary. (A relative strtotime
     * string like "+1 day" reads "now," but choosing to pass one is on the
     * caller, not the helper.)
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
