<?php

declare(strict_types=1);

namespace Arcanum\Test\Throttle;

use Arcanum\Hourglass\FrozenClock;
use Arcanum\Hourglass\SystemClock;
use Arcanum\Throttle\Quota;
use Arcanum\Throttle\SlidingWindow;
use Arcanum\Vault\ArrayDriver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(SlidingWindow::class)]
#[UsesClass(Quota::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(FrozenClock::class)]
#[UsesClass(SystemClock::class)]
final class SlidingWindowTest extends TestCase
{
    public function testFirstRequestIsAllowed(): void
    {
        $cache = new ArrayDriver();
        $window = new SlidingWindow();

        $quota = $window->attempt($cache, 'test', 10, 60);

        $this->assertTrue($quota->isAllowed());
        $this->assertSame(9, $quota->remaining);
        $this->assertSame(10, $quota->limit);
    }

    public function testRequestsUpToLimitAreAllowed(): void
    {
        $cache = new ArrayDriver();
        $window = new SlidingWindow();

        for ($i = 0; $i < 10; $i++) {
            $quota = $window->attempt($cache, 'test', 10, 60);
            $this->assertTrue($quota->isAllowed());
        }

        $this->assertSame(0, $quota->remaining);
    }

    public function testRequestBeyondLimitIsDenied(): void
    {
        $cache = new ArrayDriver();
        $window = new SlidingWindow();

        for ($i = 0; $i < 10; $i++) {
            $window->attempt($cache, 'test', 10, 60);
        }

        $quota = $window->attempt($cache, 'test', 10, 60);

        $this->assertFalse($quota->isAllowed());
        $this->assertSame(0, $quota->remaining);
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $cache = new ArrayDriver();
        $window = new SlidingWindow();

        for ($i = 0; $i < 10; $i++) {
            $window->attempt($cache, 'user-a', 10, 60);
        }

        $quota = $window->attempt($cache, 'user-b', 10, 60);

        $this->assertTrue($quota->isAllowed());
        $this->assertSame(9, $quota->remaining);
    }

    public function testWindowRotationShiftsCurrentToPrevious(): void
    {
        $cache = new ArrayDriver();
        $window = new SlidingWindow();

        // Fill the first window.
        for ($i = 0; $i < 8; $i++) {
            $window->attempt($cache, 'test', 10, 60);
        }

        // Simulate window expiry by moving the current window start back.
        /** @var array{count: int, windowStart: int} $current */
        $current = $cache->get('test_cur');
        $current['windowStart'] -= 60;
        $cache->set('test_cur', $current);

        // Next attempt triggers rotation. Previous window (8 requests) is now
        // weighted by overlap. With full window elapsed, overlap is near zero,
        // so most of the limit should be available again.
        $quota = $window->attempt($cache, 'test', 10, 60);

        $this->assertTrue($quota->isAllowed());
        $this->assertGreaterThanOrEqual(1, $quota->remaining);
    }

    public function testPreviousWindowWeightDecreasesOverTime(): void
    {
        $cache = new ArrayDriver();
        $window = new SlidingWindow();

        // Simulate a previous window with high usage that just expired.
        $now = time();
        $cache->set('test_prev', ['count' => 10, 'windowStart' => $now - 60], 120);
        $cache->set('test_cur', ['count' => 0, 'windowStart' => $now], 60);

        // Previous window ended at $now, so overlap is 0 → weight is 0.
        // All 10 slots should be available.
        $quota = $window->attempt($cache, 'test', 10, 60);

        $this->assertTrue($quota->isAllowed());
        $this->assertSame(9, $quota->remaining);
    }

    public function testPreviousWindowCountsWhenOverlapIsHigh(): void
    {
        $cache = new ArrayDriver();
        $window = new SlidingWindow();

        // Previous window that mostly overlaps with the current sliding window.
        $now = time();
        $cache->set('test_prev', ['count' => 10, 'windowStart' => $now - 10], 120);
        $cache->set('test_cur', ['count' => 0, 'windowStart' => $now], 60);

        // Previous window ends at $now+50, overlap = 50/60 ≈ 83%.
        // Weighted previous count ≈ floor(10 * 50/60) = 8.
        // 8 + 0 current = 8, under limit of 10 → allowed.
        $quota = $window->attempt($cache, 'test', 10, 60);

        $this->assertTrue($quota->isAllowed());
        $this->assertLessThanOrEqual(2, $quota->remaining);
    }

    public function testResetAtPointsToCurrentWindowEnd(): void
    {
        $cache = new ArrayDriver();
        $window = new SlidingWindow();
        $now = time();

        $quota = $window->attempt($cache, 'test', 10, 60);

        $this->assertGreaterThanOrEqual($now + 60, $quota->resetAt);
    }

    public function testFrozenClockMakesWindowRotationDeterministic(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-04-08 12:00:00'));
        $cache = new ArrayDriver($clock);
        $window = new SlidingWindow($clock);

        // Fill the window to its limit.
        for ($i = 0; $i < 10; $i++) {
            $allowed = $window->attempt($cache, 'user', 10, 60);
            $this->assertTrue($allowed->isAllowed());
        }

        // Eleventh attempt is denied.
        $denied = $window->attempt($cache, 'user', 10, 60);
        $this->assertFalse($denied->isAllowed());

        // Advance the clock past the window. The current window rotates to
        // previous; previous overlap is now 0, so the full limit is available.
        $clock->advance(new \DateInterval('PT60S'));

        $allowedAgain = $window->attempt($cache, 'user', 10, 60);
        $this->assertTrue($allowedAgain->isAllowed());
        $this->assertSame(9, $allowedAgain->remaining);
    }

    public function testDeniedQuotaCarriesRetryAfterFromClock(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-04-08 12:00:00'));
        $cache = new ArrayDriver($clock);
        $window = new SlidingWindow($clock);

        // Saturate the window.
        for ($i = 0; $i < 10; $i++) {
            $window->attempt($cache, 'user', 10, 60);
        }

        $denied = $window->attempt($cache, 'user', 10, 60);
        $this->assertFalse($denied->isAllowed());
        $this->assertGreaterThan(0, $denied->retryAfter);
        $this->assertSame($denied->resetAt - $clock->now()->getTimestamp(), $denied->retryAfter);
    }
}
