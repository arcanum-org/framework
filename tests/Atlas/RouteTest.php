<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\Route;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Route::class)]
final class RouteTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        // Arrange & Act
        $route = new Route(
            dtoClass: 'App\\Catalog\\Query\\Products\\Featured',
            handlerPrefix: '',
            format: 'json',
        );

        // Assert
        $this->assertSame('App\\Catalog\\Query\\Products\\Featured', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertSame('json', $route->format);
    }

    public function testDefaultValues(): void
    {
        // Arrange & Act
        $route = new Route(dtoClass: 'App\\Query\\Dashboard');

        // Assert
        $this->assertSame('', $route->handlerPrefix);
        $this->assertSame('json', $route->format);
    }

    public function testIsQueryReturnsTrueForQueryNamespace(): void
    {
        // Arrange
        $route = new Route(dtoClass: 'App\\Catalog\\Query\\Products\\Featured');

        // Act & Assert
        $this->assertTrue($route->isQuery());
        $this->assertFalse($route->isCommand());
    }

    public function testIsCommandReturnsTrueForCommandNamespace(): void
    {
        // Arrange
        $route = new Route(
            dtoClass: 'App\\Checkout\\Command\\SubmitPayment',
            handlerPrefix: '',
        );

        // Act & Assert
        $this->assertTrue($route->isCommand());
        $this->assertFalse($route->isQuery());
    }

    public function testIsCommandReturnsTrueWithHandlerPrefix(): void
    {
        // Arrange
        $route = new Route(
            dtoClass: 'App\\Checkout\\Command\\SubmitPayment',
            handlerPrefix: 'Post',
        );

        // Act & Assert
        $this->assertTrue($route->isCommand());
        $this->assertFalse($route->isQuery());
    }

    public function testIsCommandForDeletePrefix(): void
    {
        // Arrange
        $route = new Route(
            dtoClass: 'App\\Checkout\\Command\\SubmitPayment',
            handlerPrefix: 'Delete',
        );

        // Act & Assert
        $this->assertTrue($route->isCommand());
    }

    public function testIsCommandForPatchPrefix(): void
    {
        // Arrange
        $route = new Route(
            dtoClass: 'App\\Checkout\\Command\\SubmitPayment',
            handlerPrefix: 'Patch',
        );

        // Act & Assert
        $this->assertTrue($route->isCommand());
    }

    public function testWithFormatReturnsNewRouteWithDifferentFormat(): void
    {
        // Arrange
        $original = new Route(
            dtoClass: 'App\\Shop\\Query\\NewProducts',
            format: 'json',
        );

        // Act
        $html = $original->withFormat('html');

        // Assert
        $this->assertSame('json', $original->format);
        $this->assertSame('html', $html->format);
        $this->assertSame($original->dtoClass, $html->dtoClass);
        $this->assertSame($original->handlerPrefix, $html->handlerPrefix);
    }

    public function testWithFormatPreservesAllOtherProperties(): void
    {
        // Arrange
        $original = new Route(
            dtoClass: 'App\\Catalog\\Query\\Products\\Featured',
            handlerPrefix: '',
            format: 'json',
        );

        // Act
        $csv = $original->withFormat('csv');

        // Assert
        $this->assertSame('csv', $csv->format);
        $this->assertSame('App\\Catalog\\Query\\Products\\Featured', $csv->dtoClass);
        $this->assertSame('', $csv->handlerPrefix);
    }

    public function testIsPageDefaultsFalse(): void
    {
        // Arrange & Act
        $route = new Route(dtoClass: 'App\\Query\\Dashboard');

        // Assert
        $this->assertFalse($route->isPage());
    }

    public function testIsPageReturnsTrueWhenSet(): void
    {
        // Arrange & Act
        $route = new Route(dtoClass: 'App\\Pages\\About', isPage: true);

        // Assert
        $this->assertTrue($route->isPage());
    }

    public function testWithFormatPreservesIsPage(): void
    {
        // Arrange
        $original = new Route(dtoClass: 'App\\Pages\\About', format: 'html', isPage: true);

        // Act
        $txt = $original->withFormat('txt');

        // Assert
        $this->assertTrue($txt->isPage());
        $this->assertSame('txt', $txt->format);
    }

    public function testRouteIsImmutable(): void
    {
        // Arrange
        $original = new Route(
            dtoClass: 'App\\Query\\Dashboard',
            format: 'json',
        );

        // Act
        $modified = $original->withFormat('html');

        // Assert
        $this->assertNotSame($original, $modified);
        $this->assertSame('json', $original->format);
        $this->assertSame('html', $modified->format);
    }
}
