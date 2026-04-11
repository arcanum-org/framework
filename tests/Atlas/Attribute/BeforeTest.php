<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Attribute;

use Arcanum\Atlas\Attribute\Before;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Before::class)]
final class BeforeTest extends TestCase
{
    public function testAttributeStoresClass(): void
    {
        // Arrange & Act
        $attr = new Before('App\\Middleware\\ValidateInput');

        // Assert
        $this->assertSame('App\\Middleware\\ValidateInput', $attr->class);
    }

    public function testAttributeIsReadableViaReflection(): void
    {
        // Arrange
        $ref = new \ReflectionClass(Fixture\DtoWithBefore::class);

        // Act
        $attrs = $ref->getAttributes(Before::class);

        // Assert
        $this->assertCount(1, $attrs);
        $this->assertSame('App\\Middleware\\ValidateInput', $attrs[0]->newInstance()->class);
    }
}
