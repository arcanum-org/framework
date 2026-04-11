<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\MiddlewareRegistry;
use Arcanum\Atlas\RouteMiddleware;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MiddlewareRegistry::class)]
#[UsesClass(RouteMiddleware::class)]
final class MiddlewareRegistryTest extends TestCase
{
    public function testForReturnsEmptyMiddlewareForUnknownClass(): void
    {
        // Arrange
        $registry = new MiddlewareRegistry();

        // Act
        $mw = $registry->for('App\\Domain\\Unknown\\Command\\DoSomething');

        // Assert
        $this->assertTrue($mw->isEmpty());
    }

    public function testRegisterAndRetrieve(): void
    {
        // Arrange
        $registry = new MiddlewareRegistry();
        $mw = new RouteMiddleware(http: ['App\\Http\\Middleware\\Auth']);

        // Act
        $registry->register('App\\Domain\\Admin\\Command\\BanUser', $mw);
        $result = $registry->for('App\\Domain\\Admin\\Command\\BanUser');

        // Assert
        $this->assertSame($mw, $result);
        $this->assertSame(['App\\Http\\Middleware\\Auth'], $result->http);
    }

    public function testRegisterOverwritesPreviousEntry(): void
    {
        // Arrange
        $registry = new MiddlewareRegistry();
        $first = new RouteMiddleware(http: ['First']);
        $second = new RouteMiddleware(http: ['Second']);

        // Act
        $registry->register('App\\Command\\Foo', $first);
        $registry->register('App\\Command\\Foo', $second);

        // Assert
        $this->assertSame(['Second'], $registry->for('App\\Command\\Foo')->http);
    }
}
