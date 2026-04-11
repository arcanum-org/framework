<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Rune;

use Arcanum\Rune\Attribute\Description;

#[Description('A test fixture')]
final class DescriptionFixture
{
    public function __construct(
        #[Description('The name')]
        public readonly string $name,
        public readonly string $undescribed = '',
    ) {
    }
}
