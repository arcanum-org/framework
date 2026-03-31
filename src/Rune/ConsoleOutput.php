<?php

declare(strict_types=1);

namespace Arcanum\Rune;

/**
 * Concrete Output that writes to STDOUT and STDERR.
 *
 * Supports ANSI color codes when connected to a TTY. The --no-ansi
 * flag or non-TTY streams disable color output automatically.
 */
final class ConsoleOutput implements Output
{
    /** @var resource */
    private mixed $stdout;

    /** @var resource */
    private mixed $stderr;

    private bool $ansi;

    /**
     * ANSI escape code pattern for stripping colors.
     */
    private const ANSI_PATTERN = '/\033\[[0-9;]*m/';

    /**
     * @param resource|null $stdout Writable stream for standard output (default: STDOUT).
     * @param resource|null $stderr Writable stream for standard error (default: STDERR).
     * @param bool|null $ansi Whether to enable ANSI colors. Null auto-detects from TTY.
     */
    public function __construct(
        mixed $stdout = null,
        mixed $stderr = null,
        bool|null $ansi = null,
    ) {
        $this->stdout = $stdout ?? \STDOUT;
        $this->stderr = $stderr ?? \STDERR;
        $this->ansi = $ansi ?? $this->detectAnsi();
    }

    public function write(string $text): void
    {
        fwrite($this->stdout, $this->prepare($text));
    }

    public function writeLine(string $text): void
    {
        fwrite($this->stdout, $this->prepare($text) . \PHP_EOL);
    }

    public function error(string $text): void
    {
        fwrite($this->stderr, $this->prepare($text));
    }

    public function errorLine(string $text): void
    {
        fwrite($this->stderr, $this->prepare($text) . \PHP_EOL);
    }

    /**
     * Whether ANSI color output is enabled.
     */
    public function isAnsi(): bool
    {
        return $this->ansi;
    }

    /**
     * Strip ANSI codes if color is disabled, otherwise pass through.
     */
    private function prepare(string $text): string
    {
        if ($this->ansi) {
            return $text;
        }

        return (string) preg_replace(self::ANSI_PATTERN, '', $text);
    }

    /**
     * Auto-detect ANSI support from the stdout stream.
     */
    private function detectAnsi(): bool
    {
        if (function_exists('stream_isatty')) {
            return stream_isatty($this->stdout);
        }

        return false;
    }
}
