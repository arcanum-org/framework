<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Attribute;

use Arcanum\Atlas\Attribute\AllowedFormats;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AllowedFormats::class)]
final class AllowedFormatsTest extends TestCase
{
    public function testStoresFormatsAsLowercaseList(): void
    {
        // Arrange & Act
        $attr = new AllowedFormats('JSON', 'Html', 'csv');

        // Assert
        $this->assertSame(['json', 'html', 'csv'], $attr->formats);
    }

    public function testSingleFormat(): void
    {
        // Arrange & Act
        $attr = new AllowedFormats('json');

        // Assert
        $this->assertSame(['json'], $attr->formats);
    }

    public function testAttributeIsReadableViaReflection(): void
    {
        // Arrange
        $ref = new \ReflectionClass(Fixture\DtoWithAllowedFormats::class);

        // Act
        $attrs = $ref->getAttributes(AllowedFormats::class);

        // Assert
        $this->assertCount(1, $attrs);
        $this->assertSame(['json', 'html'], $attrs[0]->newInstance()->formats);
    }
}
