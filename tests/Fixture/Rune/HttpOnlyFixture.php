<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Rune;

use Arcanum\Hyper\Attribute\HttpOnly;

#[HttpOnly]
final class HttpOnlyFixture
{
    public function __construct(
        public readonly string $name = '',
    ) {
    }
}
