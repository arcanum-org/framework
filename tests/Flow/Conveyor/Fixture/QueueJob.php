<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

final class QueueJob
{
    public function __construct(
        public readonly string $name = '',
    ) {
    }
}
