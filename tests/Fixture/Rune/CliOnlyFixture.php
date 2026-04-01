<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Rune;

use Arcanum\Rune\Attribute\CliOnly;

#[CliOnly]
final class CliOnlyFixture
{
    public function __construct(
        public readonly string $name = '',
    ) {
    }
}
