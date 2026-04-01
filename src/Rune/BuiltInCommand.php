<?php

declare(strict_types=1);

namespace Arcanum\Rune;

/**
 * Contract for built-in framework commands.
 *
 * Built-in commands are invoked without a command:/query: prefix.
 * They receive raw CLI input and output, and return an exit code.
 */
interface BuiltInCommand
{
    public function execute(Input $input, Output $output): int;
}
