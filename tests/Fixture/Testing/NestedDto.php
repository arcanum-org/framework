<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Testing;

final class NestedDto
{
    public function __construct(
        public readonly SimpleDto $inner,
        public readonly string $label,
    ) {
    }
}
