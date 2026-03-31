<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Integration\Query;

final class Status
{
    public function __construct(
        public readonly bool $verbose = false,
    ) {
    }
}
