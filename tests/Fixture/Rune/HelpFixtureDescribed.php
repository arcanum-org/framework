<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Rune;

use Arcanum\Rune\Attribute\Description;

#[Description('A described command')]
final class HelpFixtureDescribed
{
    public function __construct(
        #[Description('Full name of the contact')]
        public readonly string $name,
        #[Description('Email address')]
        public readonly string $email,
        public readonly string $message = '',
    ) {
    }
}
