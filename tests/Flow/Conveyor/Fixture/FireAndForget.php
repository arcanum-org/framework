<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

final class FireAndForget
{
    public function __construct(
        public readonly string $name = '',
    ) {
    }
}
