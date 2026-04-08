<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Hourglass\Clock;
use Arcanum\Hourglass\Stopwatch as Timer;
use Arcanum\Hourglass\SystemClock;
use Arcanum\Ignition\Bootstrapper;

/**
 * Registers the Hourglass package: Stopwatch and Clock.
 *
 * Stopwatch is registered as a singleton and installed as the process-global
 * via Stopwatch::install() so call sites that prefer the static accessor
 * (middleware, formatter boundaries) can mark instants without taking a
 * constructor dependency. The factory reads the ARCANUM_START constant if
 * the entry point defined one (recommended — define it as the very first line
 * of public/index.php or bin/arcanum so the recorded arcanum.start instant
 * reflects the true earliest moment of the process). When the constant is
 * absent, the Stopwatch records its own construction time instead.
 *
 * Clock is registered as a singleton bound to SystemClock — the wall-clock
 * "now" boundary used by Sessions, Auth, Throttle, and Vault for time-
 * sensitive logic that needs to be fakeable in tests via FrozenClock.
 */
class Hourglass implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        $startTime = defined('ARCANUM_START') && is_float(\ARCANUM_START)
            ? \ARCANUM_START
            : null;

        $stopwatch = new Timer($startTime);

        $container->instance(Timer::class, $stopwatch);
        Timer::install($stopwatch);

        $container->instance(Clock::class, new SystemClock());
    }
}
