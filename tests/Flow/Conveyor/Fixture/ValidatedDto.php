<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

use Arcanum\Validation\Rule\NotEmpty;

final class ValidatedDto
{
    public function __construct(
        #[NotEmpty]
        public readonly string $name,
    ) {
    }
}
