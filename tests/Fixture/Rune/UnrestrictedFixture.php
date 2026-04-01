<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Rune;

final class UnrestrictedFixture
{
    public function __construct(
        public readonly string $name = '',
    ) {
    }
}
