<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Rune;

final class HelpFixtureWithBool
{
    public function __construct(
        public readonly bool $verbose = false,
    ) {
    }
}
