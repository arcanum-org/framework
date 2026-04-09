<?php

declare(strict_types=1);

namespace Arcanum\Testing;

use Arcanum\Rune\Output;

/**
 * In-memory `Output` implementation used by `CliTestSurface`.
 *
 * Captures everything written to stdout and stderr in plain strings so
 * tests can assert on them after `CliTestSurface::run()` returns. Each
 * invocation of `run()` binds a fresh BufferedOutput so that captures
 * never bleed between commands.
 */
final class BufferedOutput implements Output
{
    private string $stdout = '';
    private string $stderr = '';

    public function write(string $text): void
    {
        $this->stdout .= $text;
    }

    public function writeLine(string $text): void
    {
        $this->stdout .= $text . PHP_EOL;
    }

    public function error(string $text): void
    {
        $this->stderr .= $text;
    }

    public function errorLine(string $text): void
    {
        $this->stderr .= $text . PHP_EOL;
    }

    public function stdout(): string
    {
        return $this->stdout;
    }

    public function stderr(): string
    {
        return $this->stderr;
    }
}
