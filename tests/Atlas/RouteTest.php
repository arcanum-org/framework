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
            pathParameters: ['id' => '123'],
            format: 'json',
        );

        // Assert
        $this->assertSame('App\\Catalog\\Query\\Products\\Featured', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertSame(['id' => '123'], $route->pathParameters);
        $this->assertSame('json', $route->format);
    }

    public function testDefaultValues(): void
    {
        // Arrange & Act
        $route = new Route(dtoClass: 'App\\Query\\Dashboard');

        // Assert
        $this->assertSame('', $route->handlerPrefix);
        $this->assertSame([], $route->pathParameters);
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
        $this->assertSame($original->pathParameters, $html->pathParameters);
    }

    public function testWithFormatPreservesAllOtherProperties(): void
    {
        // Arrange
        $original = new Route(
            dtoClass: 'App\\Catalog\\Query\\Products\\Featured',
            handlerPrefix: '',
            pathParameters: ['id' => '42', 'slug' => 'widgets'],
            format: 'json',
        );

        // Act
        $csv = $original->withFormat('csv');

        // Assert
        $this->assertSame('csv', $csv->format);
        $this->assertSame('App\\Catalog\\Query\\Products\\Featured', $csv->dtoClass);
        $this->assertSame('', $csv->handlerPrefix);
        $this->assertSame(['id' => '42', 'slug' => 'widgets'], $csv->pathParameters);
    }

    public function testWithPathParametersReturnsNewRouteWithMergedParameters(): void
    {
        // Arrange
        $original = new Route(
            dtoClass: 'App\\Orders\\Query\\OrderDetails',
            pathParameters: ['id' => '123'],
        );

        // Act
        $expanded = $original->withPathParameters(['tab' => 'items']);

        // Assert
        $this->assertSame(['id' => '123'], $original->pathParameters);
        $this->assertSame(['id' => '123', 'tab' => 'items'], $expanded->pathParameters);
    }

    public function testWithPathParametersMergesOverExistingKeys(): void
    {
        // Arrange
        $original = new Route(
            dtoClass: 'App\\Orders\\Query\\OrderDetails',
            pathParameters: ['id' => '123'],
        );

        // Act
        $overridden = $original->withPathParameters(['id' => '456']);

        // Assert
        $this->assertSame(['id' => '123'], $original->pathParameters);
        $this->assertSame(['id' => '456'], $overridden->pathParameters);
    }

    public function testWithPathParametersPreservesAllOtherProperties(): void
    {
        // Arrange
        $original = new Route(
            dtoClass: 'App\\Checkout\\Command\\SubmitPayment',
            handlerPrefix: 'Post',
            pathParameters: [],
            format: 'html',
        );

        // Act
        $expanded = $original->withPathParameters(['token' => 'abc']);

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $expanded->dtoClass);
        $this->assertSame('Post', $expanded->handlerPrefix);
        $this->assertSame('html', $expanded->format);
        $this->assertSame(['token' => 'abc'], $expanded->pathParameters);
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
