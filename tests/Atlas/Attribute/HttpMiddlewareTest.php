<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas\Attribute;

use Arcanum\Atlas\Attribute\HttpMiddleware;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HttpMiddleware::class)]
final class HttpMiddlewareTest extends TestCase
{
    public function testAttributeStoresClass(): void
    {
        // Arrange & Act
        $attr = new HttpMiddleware('App\\Http\\Middleware\\Auth');

        // Assert
        $this->assertSame('App\\Http\\Middleware\\Auth', $attr->class);
    }

    public function testAttributeIsReadableViaReflection(): void
    {
        // Arrange
        $ref = new \ReflectionClass(Fixture\DtoWithHttpMiddleware::class);

        // Act
        $attrs = $ref->getAttributes(HttpMiddleware::class);

        // Assert
        $this->assertCount(2, $attrs);
        $this->assertSame('App\\Http\\Middleware\\Auth', $attrs[0]->newInstance()->class);
        $this->assertSame('App\\Http\\Middleware\\RateLimit', $attrs[1]->newInstance()->class);
    }
}
