<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\MethodNotAllowed;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\RouteMap;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(RouteMap::class)]
#[UsesClass(MethodNotAllowed::class)]
#[UsesClass(Route::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(UnresolvableRoute::class)]
final class RouteMapTest extends TestCase
{
    // -----------------------------------------------------------
    // Registration and resolution
    // -----------------------------------------------------------

    public function testRegisterAndResolveGetRoute(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/dashboard', 'App\\Admin\\Query\\Dashboard');

        // Act
        $route = $map->resolve('/dashboard', 'GET');

        // Assert
        $this->assertSame('App\\Admin\\Query\\Dashboard', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertSame('json', $route->format);
    }

    public function testRegisterWithCustomFormat(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/home', 'App\\Pages\\Home', ['GET'], 'html');

        // Act
        $route = $map->resolve('/home', 'GET');

        // Assert
        $this->assertSame('html', $route->format);
    }

    public function testExtensionFormatOverridesDefault(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/home', 'App\\Pages\\Home', ['GET'], 'html');

        // Act
        $route = $map->resolve('/home', 'GET', 'json');

        // Assert
        $this->assertSame('json', $route->format);
    }

    // -----------------------------------------------------------
    // Multiple methods
    // -----------------------------------------------------------

    public function testRegisterWithMultipleMethods(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register(
            '/legacy/endpoint',
            'App\\Compat\\Command\\Legacy',
            ['PUT', 'POST'],
        );

        // Act
        $putRoute = $map->resolve('/legacy/endpoint', 'PUT');
        $postRoute = $map->resolve('/legacy/endpoint', 'POST');

        // Assert — PUT gets no prefix, POST gets 'Post'
        $this->assertSame('', $putRoute->handlerPrefix);
        $this->assertSame('Post', $postRoute->handlerPrefix);
    }

    public function testDeleteMethodGetsDeletePrefix(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/items', 'App\\Item\\Command\\Item', ['DELETE']);

        // Act
        $route = $map->resolve('/items', 'DELETE');

        // Assert
        $this->assertSame('Delete', $route->handlerPrefix);
    }

    public function testPatchMethodGetsPatchPrefix(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/items', 'App\\Item\\Command\\Item', ['PATCH']);

        // Act
        $route = $map->resolve('/items', 'PATCH');

        // Assert
        $this->assertSame('Patch', $route->handlerPrefix);
    }

    // -----------------------------------------------------------
    // Method not allowed
    // -----------------------------------------------------------

    public function testThrowsMethodNotAllowedForWrongMethod(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/dashboard', 'App\\Admin\\Query\\Dashboard', ['GET']);

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $map->resolve('/dashboard', 'POST');
    }

    public function testMethodNotAllowedListsAllowedMethods(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/items', 'App\\Item\\Command\\Item', ['PUT', 'PATCH']);

        // Act
        try {
            $map->resolve('/items', 'GET');
            $this->fail('Expected MethodNotAllowed');
        } catch (MethodNotAllowed $e) {
            // Assert
            $this->assertSame(['PUT', 'PATCH'], $e->getAllowedMethods());
        }
    }

    // -----------------------------------------------------------
    // Unregistered path
    // -----------------------------------------------------------

    public function testThrowsForUnregisteredPath(): void
    {
        // Arrange
        $map = new RouteMap();

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $map->resolve('/nonexistent', 'GET');
    }

    // -----------------------------------------------------------
    // has()
    // -----------------------------------------------------------

    public function testHasReturnsTrueForRegisteredPath(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/dashboard', 'App\\Admin\\Query\\Dashboard');

        // Assert
        $this->assertTrue($map->has('/dashboard'));
    }

    public function testHasReturnsFalseForUnregisteredPath(): void
    {
        // Arrange
        $map = new RouteMap();

        // Assert
        $this->assertFalse($map->has('/nonexistent'));
    }

    // -----------------------------------------------------------
    // allowedMethods()
    // -----------------------------------------------------------

    public function testAllowedMethodsReturnsRegisteredMethods(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/items', 'App\\Item\\Command\\Item', ['PUT', 'POST', 'DELETE']);

        // Act
        $methods = $map->allowedMethods('/items');

        // Assert
        $this->assertSame(['PUT', 'POST', 'DELETE'], $methods);
    }

    public function testAllowedMethodsReturnsEmptyForUnregistered(): void
    {
        // Arrange
        $map = new RouteMap();

        // Act
        $methods = $map->allowedMethods('/nonexistent');

        // Assert
        $this->assertSame([], $methods);
    }

    // -----------------------------------------------------------
    // Path normalization
    // -----------------------------------------------------------

    public function testTrailingSlashNormalized(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/dashboard/', 'App\\Admin\\Query\\Dashboard');

        // Act
        $route = $map->resolve('/dashboard', 'GET');

        // Assert
        $this->assertSame('App\\Admin\\Query\\Dashboard', $route->dtoClass);
    }

    public function testMethodsCaseInsensitive(): void
    {
        // Arrange
        $map = new RouteMap();
        $map->register('/items', 'App\\Item\\Command\\Item', ['get']);

        // Act
        $route = $map->resolve('/items', 'GET');

        // Assert
        $this->assertSame('App\\Item\\Command\\Item', $route->dtoClass);
    }
}
