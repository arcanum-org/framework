<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Testing;

use Arcanum\Validation\Rule\Callback;

final class CallbackDto
{
    public function __construct(
        #[Callback([self::class, 'check'])]
        public readonly string $token,
    ) {
    }

    public static function check(mixed $value): true|string
    {
        return is_string($value) && $value !== '' ? true : 'token must be a non-empty string';
    }
}
