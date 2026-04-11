<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper\Attribute;

use Arcanum\Hyper\Attribute\HttpOnly;
use Arcanum\Test\Fixture\Rune\HttpOnlyFixture;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HttpOnly::class)]
final class HttpOnlyTest extends TestCase
{
    public function testAttributeIsReadableViaReflection(): void
    {
        // Arrange
        $ref = new \ReflectionClass(HttpOnlyFixture::class);

        // Act
        $attrs = $ref->getAttributes(HttpOnly::class);

        // Assert
        $this->assertCount(1, $attrs);
        $this->assertInstanceOf(HttpOnly::class, $attrs[0]->newInstance());
    }
}
