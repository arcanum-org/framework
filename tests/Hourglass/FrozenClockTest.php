<?php

declare(strict_types=1);

namespace Arcanum\Test\Hourglass;

use Arcanum\Hourglass\FrozenClock;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FrozenClock::class)]
final class FrozenClockTest extends TestCase
{
    public function testNowReturnsTheFrozenInstant(): void
    {
        $fixed = new DateTimeImmutable('2026-04-07T12:00:00Z');
        $clock = new FrozenClock($fixed);

        $this->assertEquals($fixed, $clock->now());
        $this->assertEquals($fixed, $clock->now());
    }

    public function testSetReplacesTheFrozenInstant(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-04-07T12:00:00Z'));

        $next = new DateTimeImmutable('2026-04-08T00:00:00Z');
        $clock->set($next);

        $this->assertEquals($next, $clock->now());
    }

    public function testAdvanceMovesForwardByInterval(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-04-07T12:00:00Z'));

        $clock->advance(new DateInterval('PT1H'));

        $this->assertEquals(
            new DateTimeImmutable('2026-04-07T13:00:00Z'),
            $clock->now(),
        );
    }
}
