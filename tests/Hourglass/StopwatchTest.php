<?php

declare(strict_types=1);

namespace Arcanum\Test\Hourglass;

use Arcanum\Hourglass\Instant;
use Arcanum\Hourglass\Stopwatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Stopwatch::class)]
#[UsesClass(Instant::class)]
final class StopwatchTest extends TestCase
{
    protected function tearDown(): void
    {
        Stopwatch::uninstall();
    }

    public function testConstructorRecordsArcanumStart(): void
    {
        $stopwatch = new Stopwatch();

        $this->assertTrue($stopwatch->has('arcanum.start'));
        $marks = $stopwatch->marks();
        $this->assertCount(1, $marks);
        $this->assertSame('arcanum.start', $marks[0]->label);
    }

    public function testConstructorAcceptsExplicitStartTime(): void
    {
        $stopwatch = new Stopwatch(1712534400.0);

        $this->assertSame(1712534400.0, $stopwatch->startTime());
        $this->assertSame(1712534400.0, $stopwatch->marks()[0]->time);
    }

    public function testStartTimeDefaultsToConstructionMoment(): void
    {
        $before = microtime(true);
        $stopwatch = new Stopwatch();
        $after = microtime(true);

        $this->assertGreaterThanOrEqual($before, $stopwatch->startTime());
        $this->assertLessThanOrEqual($after, $stopwatch->startTime());
    }

    public function testMarkAppendsInstantsInInsertionOrder(): void
    {
        $stopwatch = new Stopwatch(1000.0);

        $stopwatch->mark('boot.complete', 1000.5);
        $stopwatch->mark('request.received', 1001.0);
        $stopwatch->mark('handler.start', 1001.25);

        $marks = $stopwatch->marks();
        $this->assertSame(
            ['arcanum.start', 'boot.complete', 'request.received', 'handler.start'],
            array_map(fn (Instant $i) => $i->label, $marks),
        );
        $this->assertSame(
            [1000.0, 1000.5, 1001.0, 1001.25],
            array_map(fn (Instant $i) => $i->time, $marks),
        );
    }

    public function testMarkPreservesDuplicateLabels(): void
    {
        $stopwatch = new Stopwatch(1000.0);

        $stopwatch->mark('handler.start', 1001.0);
        $stopwatch->mark('handler.start', 1002.0);

        $this->assertCount(3, $stopwatch->marks());
    }

    public function testHasReturnsFalseForUnknownLabel(): void
    {
        $stopwatch = new Stopwatch();

        $this->assertFalse($stopwatch->has('never.recorded'));
    }

    public function testTimeSinceUsesTheMostRecentMatchingInstant(): void
    {
        $stopwatch = new Stopwatch(1000.0);
        $stopwatch->mark('handler.start', microtime(true) - 0.5);
        $stopwatch->mark('handler.start', microtime(true) - 0.1);

        $elapsed = $stopwatch->timeSince('handler.start');

        $this->assertNotNull($elapsed);
        // Most recent handler.start is ~100ms ago, not ~500ms.
        $this->assertGreaterThanOrEqual(90.0, $elapsed);
        $this->assertLessThan(300.0, $elapsed);
    }

    public function testTimeSinceReturnsNullForUnknownLabel(): void
    {
        $stopwatch = new Stopwatch();

        $this->assertNull($stopwatch->timeSince('never.recorded'));
    }

    public function testTimeBetweenReturnsMillisecondDelta(): void
    {
        $stopwatch = new Stopwatch(1000.0);
        $stopwatch->mark('boot.complete', 1000.25);

        $delta = $stopwatch->timeBetween('arcanum.start', 'boot.complete');

        $this->assertSame(250.0, $delta);
    }

    public function testTimeBetweenUsesFirstOccurrenceOfEachLabel(): void
    {
        $stopwatch = new Stopwatch(1000.0);
        $stopwatch->mark('handler.start', 1001.0);
        $stopwatch->mark('handler.start', 1002.0);

        $this->assertSame(1000.0, $stopwatch->timeBetween('arcanum.start', 'handler.start'));
    }

    public function testTimeBetweenReturnsNullWhenFromMissing(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->mark('boot.complete');

        $this->assertNull($stopwatch->timeBetween('nope', 'boot.complete'));
    }

    public function testTimeBetweenReturnsNullWhenToMissing(): void
    {
        $stopwatch = new Stopwatch();

        $this->assertNull($stopwatch->timeBetween('arcanum.start', 'nope'));
    }

    public function testCurrentThrowsWhenNoStopwatchInstalled(): void
    {
        $this->expectException(RuntimeException::class);
        Stopwatch::current();
    }

    public function testInstallAndCurrentReturnTheSameInstance(): void
    {
        $stopwatch = new Stopwatch();
        Stopwatch::install($stopwatch);

        $this->assertSame($stopwatch, Stopwatch::current());
    }

    public function testUninstallClearsTheCurrentInstance(): void
    {
        Stopwatch::install(new Stopwatch());
        Stopwatch::uninstall();

        $this->expectException(RuntimeException::class);
        Stopwatch::current();
    }
}
