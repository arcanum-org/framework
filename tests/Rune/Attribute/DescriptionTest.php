<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Attribute;

use Arcanum\Rune\Attribute\Description;
use Arcanum\Test\Fixture\Rune\DescriptionFixture;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Description::class)]
#[UsesClass(DescriptionFixture::class)]
final class DescriptionTest extends TestCase
{
    public function testStoresText(): void
    {
        // Arrange & Act
        $desc = new Description('Submit a contact form');

        // Assert
        $this->assertSame('Submit a contact form', $desc->text);
    }

    public function testReadFromClassAttribute(): void
    {
        // Arrange
        $ref = new \ReflectionClass(DescriptionFixture::class);

        // Act
        $attrs = $ref->getAttributes(Description::class);

        // Assert
        $this->assertCount(1, $attrs);
        $this->assertSame('A test fixture', $attrs[0]->newInstance()->text);
    }

    public function testReadFromParameterAttribute(): void
    {
        // Arrange
        $ref = new \ReflectionClass(DescriptionFixture::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $param = $constructor->getParameters()[0];

        // Act
        $attrs = $param->getAttributes(Description::class);

        // Assert
        $this->assertCount(1, $attrs);
        $this->assertSame('The name', $attrs[0]->newInstance()->text);
    }

    public function testAbsentOnUndecoratedParameter(): void
    {
        // Arrange
        $ref = new \ReflectionClass(DescriptionFixture::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $param = $constructor->getParameters()[1];

        // Act
        $attrs = $param->getAttributes(Description::class);

        // Assert
        $this->assertCount(0, $attrs);
    }
}
