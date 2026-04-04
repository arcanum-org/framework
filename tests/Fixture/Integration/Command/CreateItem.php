<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Integration\Command;

final class CreateItem
{
    public function __construct(
        public readonly string $name = '',
    ) {
    }
}
