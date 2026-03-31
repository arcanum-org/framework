<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Fixture\Middleware\Public\Query;

final class Status
{
    public function __construct(
        public readonly bool $verbose = false,
    ) {
    }
}
