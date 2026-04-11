<?php

declare(strict_types=1);

namespace Arcanum\Test\Hourglass;

use Arcanum\Hourglass\Interval;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Interval::class)]
final class IntervalTest extends TestCase
{
    public function testSecondsInZeroInterval(): void
    {
        $this->assertSame(0, Interval::secondsIn(new \DateInterval('PT0S')));
    }

    public function testSecondsInOneSecond(): void
    {
        $this->assertSame(1, Interval::secondsIn(new \DateInterval('PT1S')));
    }

    public function testSecondsInOneMinute(): void
    {
        $this->assertSame(60, Interval::secondsIn(new \DateInterval('PT1M')));
    }

    public function testSecondsInOneHour(): void
    {
        $this->assertSame(3600, Interval::secondsIn(new \DateInterval('PT1H')));
    }

    public function testSecondsInOneDay(): void
    {
        $this->assertSame(86400, Interval::secondsIn(new \DateInterval('P1D')));
    }

    public function testSecondsInMixedHmsInterval(): void
    {
        // 1h 30m 15s = 5415 seconds
        $this->assertSame(5415, Interval::secondsIn(new \DateInterval('PT1H30M15S')));
    }

    public function testSecondsInMixedDhmsInterval(): void
    {
        // 2 days 3 hours = 2*86400 + 3*3600 = 183600
        $this->assertSame(183600, Interval::secondsIn(new \DateInterval('P2DT3H')));
    }

    public function testSecondsInOneMonthIsNormalizedTo31Days(): void
    {
        // Documented behavior: epoch-anchor places 1 month at Jan→Feb 1970,
        // which is 31 days = 2_678_400 seconds.
        $this->assertSame(31 * 86400, Interval::secondsIn(new \DateInterval('P1M')));
    }

    public function testSecondsInOneYearIsNormalizedTo365Days(): void
    {
        // Documented behavior: epoch-anchor (1970 is non-leap) → 365 days.
        $this->assertSame(365 * 86400, Interval::secondsIn(new \DateInterval('P1Y')));
    }

    public function testOfSecondsZero(): void
    {
        $interval = Interval::ofSeconds(0);

        $this->assertInstanceOf(\DateInterval::class, $interval);
        $this->assertSame(0, Interval::secondsIn($interval));
    }

    public function testOfSecondsOneHundred(): void
    {
        $interval = Interval::ofSeconds(100);

        $this->assertSame(100, Interval::secondsIn($interval));
    }

    public function testOfSecondsClampsNegativeToZero(): void
    {
        $interval = Interval::ofSeconds(-42);

        $this->assertSame(0, Interval::secondsIn($interval));
    }

    public function testRoundTripPreservesValue(): void
    {
        // Pick an arbitrary count of seconds and round-trip it through both methods.
        $original = 7385; // 2h 3m 5s
        $interval = Interval::ofSeconds($original);

        $this->assertSame($original, Interval::secondsIn($interval));
    }
}
