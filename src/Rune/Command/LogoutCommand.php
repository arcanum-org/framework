<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Auth\CliSession;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

/**
 * Clears the CLI session.
 *
 * Usage: php arcanum logout
 */
#[Description('Log out of the CLI session')]
final class LogoutCommand implements BuiltInCommand
{
    public function __construct(
        private readonly CliSession $session,
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        $this->session->clear();
        $output->writeLine('Logged out.');

        return ExitCode::Success->value;
    }
}
