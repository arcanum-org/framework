<?php

declare(strict_types=1);

namespace Arcanum\Testing;

/**
 * Immutable record of one CLI invocation through `CliTestSurface::run()`.
 */
final class CliResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
    ) {
    }
}
