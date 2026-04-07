<?php

declare(strict_types=1);

namespace Arcanum\Hourglass;

/**
 * A labeled point in time captured by a Stopwatch.
 *
 * Time is a high-resolution monotonic-ish float from microtime(true).
 * Instants are immutable and self-describing — they carry their own label
 * so a list<Instant> reads as a complete timeline.
 */
final class Instant
{
    public function __construct(
        public readonly string $label,
        public readonly float $time,
    ) {
    }
}
