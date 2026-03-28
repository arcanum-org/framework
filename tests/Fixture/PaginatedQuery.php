<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture;

final class PaginatedQuery
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 25,
        public readonly bool $includeArchived = false,
    ) {
    }
}
