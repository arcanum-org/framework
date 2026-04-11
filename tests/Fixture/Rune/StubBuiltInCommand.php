<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Rune;

use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;

final class StubBuiltInCommand implements BuiltInCommand
{
    public function execute(Input $input, Output $output): int
    {
        return ExitCode::Success->value;
    }
}
