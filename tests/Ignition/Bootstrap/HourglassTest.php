<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Container;
use Arcanum\Hourglass\Clock;
use Arcanum\Hourglass\Stopwatch;
use Arcanum\Hourglass\SystemClock;
use Arcanum\Ignition\Bootstrap\Hourglass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Hourglass::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(Stopwatch::class)]
#[UsesClass(SystemClock::class)]
#[UsesClass(\Arcanum\Hourglass\Instant::class)]
final class HourglassTest extends TestCase
{
    protected function tearDown(): void
    {
        // Stopwatch installs itself as a process global; clean up so other
        // tests don't see leftover state from this one.
        Stopwatch::uninstall();
    }

    public function testRegistersStopwatchAsContainerSingleton(): void
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        (new Hourglass())->bootstrap($container);

        $stopwatch = $container->get(Stopwatch::class);
        $this->assertInstanceOf(Stopwatch::class, $stopwatch);

        // Resolving again returns the same instance — singleton, not factory.
        $this->assertSame($stopwatch, $container->get(Stopwatch::class));
    }

    public function testInstallsStopwatchAsProcessGlobal(): void
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        (new Hourglass())->bootstrap($container);

        $this->assertTrue(Stopwatch::isInstalled());
        $this->assertSame($container->get(Stopwatch::class), Stopwatch::current());
    }

    public function testRecordsArcanumStartInstant(): void
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        (new Hourglass())->bootstrap($container);

        /** @var Stopwatch $stopwatch */
        $stopwatch = $container->get(Stopwatch::class);
        $this->assertTrue($stopwatch->has('arcanum.start'));
    }

    public function testRegistersClockBoundToSystemClock(): void
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        (new Hourglass())->bootstrap($container);

        $clock = $container->get(Clock::class);
        $this->assertInstanceOf(Clock::class, $clock);
        $this->assertInstanceOf(SystemClock::class, $clock);
    }

    public function testClockResolvesAsSingleton(): void
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        (new Hourglass())->bootstrap($container);

        $first = $container->get(Clock::class);
        $second = $container->get(Clock::class);
        $this->assertSame($first, $second);
    }
}
