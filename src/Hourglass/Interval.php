<?php

declare(strict_types=1);

namespace Arcanum\Hourglass;

/**
 * Time-interval primitives. Stateless conversions between PHP's `DateInterval`
 * representation and integer seconds.
 *
 * Lives in Hourglass because it's a time primitive — any code dealing with
 * PSR-16 TTLs (`\DateInterval|int|null`) needs to normalize the interval
 * branch into seconds, and that conversion shouldn't be re-implemented in
 * every cache driver. Helper-only on purpose: no subclassing of `\DateInterval`
 * keeps Hourglass free of inheritance from PHP built-ins and avoids inheriting
 * `DateInterval`'s mutability.
 */
final class Interval
{
    /**
     * Convert any `\DateInterval` into a count of seconds.
     *
     * Anchors a `DateTime` at the unix epoch (timestamp 0), adds the interval,
     * and reads the resulting timestamp — i.e. "epoch + interval = total seconds
     * in the interval." This anchor matters for month and year components,
     * which don't have a fixed length: `P1M` (1 month) is normalized to 31 days
     * because epoch + 1 month lands on Feb 1, 1970, and `P1Y` (1 year) is
     * normalized to 365 days for the same reason. For hour/minute/second/day
     * intervals — the overwhelmingly common case for cache TTLs — the
     * conversion is exact.
     */
    public static function secondsIn(\DateInterval $interval): int
    {
        return (int) (new \DateTime())->setTimestamp(0)->add($interval)->getTimestamp();
    }

    /**
     * Construct a `\DateInterval` representing the given count of seconds.
     *
     * Negative inputs are clamped to zero. Use `Interval::secondsIn()` to
     * round-trip back to an integer.
     */
    public static function ofSeconds(int $seconds): \DateInterval
    {
        $clamped = max(0, $seconds);
        return new \DateInterval("PT{$clamped}S");
    }
}
