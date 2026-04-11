<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Attribute;

use Arcanum\Rune\Attribute\CliOnly;
use Arcanum\Test\Fixture\Rune\CliOnlyFixture;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CliOnly::class)]
final class CliOnlyTest extends TestCase
{
    public function testAttributeIsReadableViaReflection(): void
    {
        // Arrange
        $ref = new \ReflectionClass(CliOnlyFixture::class);

        // Act
        $attrs = $ref->getAttributes(CliOnly::class);

        // Assert
        $this->assertCount(1, $attrs);
        $this->assertInstanceOf(CliOnly::class, $attrs[0]->newInstance());
    }
}
