<?php

declare(strict_types=1);

namespace Arcanum\Test\Hourglass;

use Arcanum\Hourglass\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    public function testNowReturnsCurrentTime(): void
    {
        $clock = new SystemClock();

        $before = microtime(true);
        $now = $clock->now();
        $after = microtime(true);

        $nowFloat = (float) $now->format('U.u');
        $this->assertGreaterThanOrEqual($before - 0.001, $nowFloat);
        $this->assertLessThanOrEqual($after + 0.001, $nowFloat);
    }

    public function testTwoCallsCanReturnDistinctInstances(): void
    {
        $clock = new SystemClock();

        $a = $clock->now();
        $b = $clock->now();

        $this->assertNotSame($a, $b);
    }
}
