<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Testing;

final class NullableDto
{
    public function __construct(
        public readonly string|null $note,
        public readonly int $count,
    ) {
    }
}
