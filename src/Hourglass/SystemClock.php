<?php

declare(strict_types=1);

namespace Arcanum\Hourglass;

use DateTimeImmutable;

/**
 * Production clock — returns the current system time.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
