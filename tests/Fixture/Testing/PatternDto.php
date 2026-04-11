<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Testing;

use Arcanum\Validation\Rule\Pattern;

final class PatternDto
{
    public function __construct(
        #[Pattern('/^[A-Z]{3}-\d{4}$/')]
        public readonly string $code,
    ) {
    }
}
