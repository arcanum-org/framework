<?php

declare(strict_types=1);

namespace Arcanum\Hourglass;

use DateInterval;
use DateTimeImmutable;

/**
 * A clock pinned to an explicit instant.
 *
 * Reads always return the same DateTimeImmutable until set() replaces it
 * or advance() moves it forward by an interval. Time only changes when the
 * caller asks it to.
 *
 * Useful anywhere code needs a stable, caller-controlled "now" — replaying
 * historical events at their original timestamps, batch jobs that should
 * treat every record as having happened at the batch's logical start,
 * deterministic scheduling tests, fixed-point simulations, and so on.
 */
final class FrozenClock implements Clock
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function advance(DateInterval $interval): void
    {
        $this->now = $this->now->add($interval);
    }
}
