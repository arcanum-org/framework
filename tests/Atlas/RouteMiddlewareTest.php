<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\RouteMiddleware;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RouteMiddleware::class)]
final class RouteMiddlewareTest extends TestCase
{
    public function testDefaultsToEmpty(): void
    {
        // Arrange & Act
        $mw = new RouteMiddleware();

        // Assert
        $this->assertSame([], $mw->http);
        $this->assertSame([], $mw->before);
        $this->assertSame([], $mw->after);
    }

    public function testIsEmptyReturnsTrueWhenAllListsEmpty(): void
    {
        // Arrange
        $mw = new RouteMiddleware();

        // Act & Assert
        $this->assertTrue($mw->isEmpty());
    }

    public function testIsEmptyReturnsFalseWithHttpMiddleware(): void
    {
        // Arrange
        $mw = new RouteMiddleware(http: ['App\\Http\\Middleware\\Auth']);

        // Act & Assert
        $this->assertFalse($mw->isEmpty());
    }

    public function testIsEmptyReturnsFalseWithBeforeMiddleware(): void
    {
        // Arrange
        $mw = new RouteMiddleware(before: ['App\\Middleware\\Validate']);

        // Act & Assert
        $this->assertFalse($mw->isEmpty());
    }

    public function testIsEmptyReturnsFalseWithAfterMiddleware(): void
    {
        // Arrange
        $mw = new RouteMiddleware(after: ['App\\Middleware\\Audit']);

        // Act & Assert
        $this->assertFalse($mw->isEmpty());
    }

    public function testMergeDirectoryOuterAttributeInner(): void
    {
        // Arrange — directory middleware is "this" (outer), attribute is "inner"
        $directory = new RouteMiddleware(
            http: ['DirHttp'],
            before: ['DirBefore'],
            after: ['DirAfter'],
        );
        $attribute = new RouteMiddleware(
            http: ['AttrHttp'],
            before: ['AttrBefore'],
            after: ['AttrAfter'],
        );

        // Act
        $merged = $directory->merge($attribute);

        // Assert — http/before: directory first (outer), attribute second (inner)
        $this->assertSame(['DirHttp', 'AttrHttp'], $merged->http);
        $this->assertSame(['DirBefore', 'AttrBefore'], $merged->before);
        // after: attribute first (inner runs first), directory second (outer runs last)
        $this->assertSame(['AttrAfter', 'DirAfter'], $merged->after);
    }

    public function testMergeWithEmptyInner(): void
    {
        // Arrange
        $directory = new RouteMiddleware(http: ['DirHttp'], before: ['DirBefore'], after: ['DirAfter']);
        $empty = new RouteMiddleware();

        // Act
        $merged = $directory->merge($empty);

        // Assert
        $this->assertSame(['DirHttp'], $merged->http);
        $this->assertSame(['DirBefore'], $merged->before);
        $this->assertSame(['DirAfter'], $merged->after);
    }

    public function testMergeWithEmptyOuter(): void
    {
        // Arrange
        $empty = new RouteMiddleware();
        $attribute = new RouteMiddleware(http: ['AttrHttp'], before: ['AttrBefore'], after: ['AttrAfter']);

        // Act
        $merged = $empty->merge($attribute);

        // Assert
        $this->assertSame(['AttrHttp'], $merged->http);
        $this->assertSame(['AttrBefore'], $merged->before);
        $this->assertSame(['AttrAfter'], $merged->after);
    }

    public function testMergeIsNotMutating(): void
    {
        // Arrange
        $a = new RouteMiddleware(http: ['A']);
        $b = new RouteMiddleware(http: ['B']);

        // Act
        $merged = $a->merge($b);

        // Assert — originals unchanged
        $this->assertSame(['A'], $a->http);
        $this->assertSame(['B'], $b->http);
        $this->assertSame(['A', 'B'], $merged->http);
    }
}
