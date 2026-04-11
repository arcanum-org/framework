<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Attribute;

use Arcanum\Atlas\Attribute\After;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(After::class)]
final class AfterTest extends TestCase
{
    public function testAttributeStoresClass(): void
    {
        // Arrange & Act
        $attr = new After('App\\Middleware\\AuditLog');

        // Assert
        $this->assertSame('App\\Middleware\\AuditLog', $attr->class);
    }

    public function testAttributeIsReadableViaReflection(): void
    {
        // Arrange
        $ref = new \ReflectionClass(Fixture\DtoWithAfter::class);

        // Act
        $attrs = $ref->getAttributes(After::class);

        // Assert
        $this->assertCount(1, $attrs);
        $this->assertSame('App\\Middleware\\AuditLog', $attrs[0]->newInstance()->class);
    }
}
