<?php

declare(strict_types=1);

namespace Arcanum\Rune;

/**
 * Contract for writing CLI output to stdout and stderr.
 */
interface Output
{
    /**
     * Write a string to standard output.
     */
    public function write(string $text): void;

    /**
     * Write a string followed by a newline to standard output.
     */
    public function writeLine(string $text): void;

    /**
     * Write a string to standard error.
     */
    public function error(string $text): void;

    /**
     * Write a string followed by a newline to standard error.
     */
    public function errorLine(string $text): void;
}
