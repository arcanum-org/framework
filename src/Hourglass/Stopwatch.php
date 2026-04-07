<?php

declare(strict_types=1);

namespace Arcanum\Hourglass;

use RuntimeException;

/**
 * Records labeled instants across a process lifetime.
 *
 * Stopwatch is the framework-wide timeline recorder. The constructor captures
 * an `arcanum.start` instant — pass an explicit start time (e.g. the value of
 * an early `ARCANUM_START` constant defined as the first line of the entry
 * point) so the recorded start reflects the true earliest moment of the process,
 * not the moment the container resolved the Stopwatch.
 *
 * Storage is an insertion-ordered list of Instants. Duplicate labels are
 * preserved — the same label may be marked multiple times across a request,
 * and the timeline is the truth.
 *
 * For ergonomic access from call sites where injection would be noisy
 * (middleware, formatter boundaries), the bootstrap layer installs the
 * resolved instance via Stopwatch::install(); call sites then read it via
 * Stopwatch::current(). Tests should install a fresh instance per test and
 * uninstall it in tearDown.
 */
final class Stopwatch
{
    /** @var list<Instant> */
    private array $instants = [];

    private static ?self $current = null;

    public function __construct(?float $startTime = null)
    {
        $this->instants[] = new Instant('arcanum.start', $startTime ?? microtime(true));
    }

    /**
     * Record an instant with the current time (or an explicit time, for tests).
     */
    public function mark(string $label, ?float $time = null): void
    {
        $this->instants[] = new Instant($label, $time ?? microtime(true));
    }

    public function has(string $label): bool
    {
        foreach ($this->instants as $instant) {
            if ($instant->label === $label) {
                return true;
            }
        }
        return false;
    }

    /**
     * Milliseconds elapsed since the most recent instant with the given label.
     *
     * Returns null if no instant with that label has been recorded.
     */
    public function timeSince(string $label): ?float
    {
        for ($i = count($this->instants) - 1; $i >= 0; $i--) {
            if ($this->instants[$i]->label === $label) {
                return (microtime(true) - $this->instants[$i]->time) * 1000.0;
            }
        }
        return null;
    }

    /**
     * Milliseconds between the first occurrence of $from and the first
     * occurrence of $to. Returns null if either label is missing.
     */
    public function timeBetween(string $from, string $to): ?float
    {
        $fromTime = null;
        $toTime = null;
        foreach ($this->instants as $instant) {
            if ($fromTime === null && $instant->label === $from) {
                $fromTime = $instant->time;
            }
            if ($toTime === null && $instant->label === $to) {
                $toTime = $instant->time;
            }
        }
        if ($fromTime === null || $toTime === null) {
            return null;
        }
        return ($toTime - $fromTime) * 1000.0;
    }

    /**
     * The microtime(true) value of the first recorded instant
     * (i.e. arcanum.start).
     */
    public function startTime(): float
    {
        return $this->instants[0]->time;
    }

    /**
     * @return list<Instant>
     */
    public function marks(): array
    {
        return $this->instants;
    }

    public static function install(self $stopwatch): void
    {
        self::$current = $stopwatch;
    }

    /**
     * Record an instant on the installed Stopwatch.
     *
     * No-op when no Stopwatch is installed. This is the right call for
     * write-only sites (middleware, formatter boundaries, listeners) — code
     * that wants to read the timeline (renderDurationMs, log lines, debug
     * toolbars) should use current() so missing-Stopwatch surfaces loudly.
     *
     * Named tap() rather than mark() to keep the static and instance APIs
     * unambiguous: instances mark, the global timeline gets tapped.
     */
    public static function tap(string $label, ?float $time = null): void
    {
        self::$current?->mark($label, $time);
    }

    public static function isInstalled(): bool
    {
        return self::$current !== null;
    }

    public static function current(): self
    {
        if (self::$current === null) {
            throw new RuntimeException(
                'No Stopwatch installed. Call Stopwatch::install() during bootstrap '
                . 'before any code calls Stopwatch::current().',
            );
        }
        return self::$current;
    }

    public static function uninstall(): void
    {
        self::$current = null;
    }
}
