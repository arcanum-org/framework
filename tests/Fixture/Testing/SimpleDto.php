<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Testing;

use Arcanum\Validation\Rule\Email;
use Arcanum\Validation\Rule\In;
use Arcanum\Validation\Rule\Max;
use Arcanum\Validation\Rule\MaxLength;
use Arcanum\Validation\Rule\Min;
use Arcanum\Validation\Rule\MinLength;
use Arcanum\Validation\Rule\NotEmpty;
use Arcanum\Validation\Rule\Url;
use Arcanum\Validation\Rule\Uuid;

final class SimpleDto
{
    public function __construct(
        #[NotEmpty]
        public readonly string $name,
        #[Email]
        public readonly string $email,
        #[Url]
        public readonly string $homepage,
        #[Uuid]
        public readonly string $id,
        #[MinLength(8)]
        #[MaxLength(12)]
        public readonly string $username,
        #[Min(18)]
        #[Max(99)]
        public readonly int $age,
        #[In('red', 'green', 'blue')]
        public readonly string $color,
        public readonly bool $active,
        public readonly string $message = 'default',
    ) {
    }
}
