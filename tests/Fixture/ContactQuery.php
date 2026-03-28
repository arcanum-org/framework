<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture;

final class ContactQuery
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $message = '',
    ) {
    }
}
