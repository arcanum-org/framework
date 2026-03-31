<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Atlas\Route;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Rune\CliRouteMap;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CliRouteMap::class)]
#[UsesClass(Route::class)]
final class CliRouteMapTest extends TestCase
{
    // ---------------------------------------------------------------
    // Registration and lookup
    // ---------------------------------------------------------------

    public function testHasReturnsFalseForUnregisteredName(): void
    {
        // Arrange
        $map = new CliRouteMap();

        // Act & Assert
        $this->assertFalse($map->has('stripe:webhook'));
    }

    public function testHasReturnsTrueForRegisteredName(): void
    {
        // Arrange
        $map = new CliRouteMap();
        $map->register('stripe:webhook', 'App\\Integration\\Stripe\\ProcessWebhook', 'command');

        // Act & Assert
        $this->assertTrue($map->has('stripe:webhook'));
    }

    public function testRegisterDefaultsToCommandType(): void
    {
        // Arrange
        $map = new CliRouteMap();
        $map->register('stripe:webhook', 'App\\Integration\\Stripe\\ProcessWebhook');

        // Act
        $route = $map->resolve('stripe:webhook');

        // Assert
        $this->assertSame('App\\Integration\\Stripe\\ProcessWebhook', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // Resolution
    // ---------------------------------------------------------------

    public function testResolveReturnsRouteForRegisteredCommand(): void
    {
        // Arrange
        $map = new CliRouteMap();
        $map->register('stripe:webhook', 'App\\Integration\\Stripe\\ProcessWebhook', 'command');

        // Act
        $route = $map->resolve('stripe:webhook');

        // Assert
        $this->assertSame('App\\Integration\\Stripe\\ProcessWebhook', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
    }

    public function testResolveReturnsRouteForRegisteredQuery(): void
    {
        // Arrange
        $map = new CliRouteMap();
        $map->register('status', 'App\\Monitoring\\SystemStatus', 'query');

        // Act
        $route = $map->resolve('status');

        // Assert
        $this->assertSame('App\\Monitoring\\SystemStatus', $route->dtoClass);
    }

    public function testResolveUsesProvidedFormat(): void
    {
        // Arrange
        $map = new CliRouteMap();
        $map->register('stripe:webhook', 'App\\Integration\\Stripe\\ProcessWebhook');

        // Act
        $route = $map->resolve('stripe:webhook', 'json');

        // Assert
        $this->assertSame('json', $route->format);
    }

    public function testResolveDefaultsToCliFormat(): void
    {
        // Arrange
        $map = new CliRouteMap();
        $map->register('stripe:webhook', 'App\\Integration\\Stripe\\ProcessWebhook');

        // Act
        $route = $map->resolve('stripe:webhook');

        // Assert
        $this->assertSame('cli', $route->format);
    }

    public function testResolveThrowsForUnregisteredName(): void
    {
        // Arrange
        $map = new CliRouteMap();

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('No custom CLI route registered for "unknown"');
        $map->resolve('unknown');
    }

    // ---------------------------------------------------------------
    // Type validation
    // ---------------------------------------------------------------

    public function testRegisterAcceptsQueryType(): void
    {
        // Arrange
        $map = new CliRouteMap();

        // Act — should not throw
        $map->register('status', 'App\\Status', 'query');

        // Assert
        $this->assertTrue($map->has('status'));
    }

    public function testRegisterAcceptsCommandType(): void
    {
        // Arrange
        $map = new CliRouteMap();

        // Act — should not throw
        $map->register('deploy', 'App\\Deploy', 'command');

        // Assert
        $this->assertTrue($map->has('deploy'));
    }

    public function testRegisterTypeIsCaseInsensitive(): void
    {
        // Arrange
        $map = new CliRouteMap();

        // Act — should not throw
        $map->register('deploy', 'App\\Deploy', 'Command');

        // Assert
        $this->assertTrue($map->has('deploy'));
    }

    public function testRegisterThrowsForInvalidType(): void
    {
        // Arrange
        $map = new CliRouteMap();

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CLI route type "event"');
        $map->register('hook', 'App\\Hook', 'event');
    }

    // ---------------------------------------------------------------
    // Names listing
    // ---------------------------------------------------------------

    public function testNamesReturnsEmptyArrayWhenNoRoutesRegistered(): void
    {
        // Arrange
        $map = new CliRouteMap();

        // Act & Assert
        $this->assertSame([], $map->names());
    }

    public function testNamesReturnsAllRegisteredNames(): void
    {
        // Arrange
        $map = new CliRouteMap();
        $map->register('stripe:webhook', 'App\\Stripe\\Webhook');
        $map->register('status', 'App\\Status', 'query');

        // Act
        $names = $map->names();

        // Assert
        $this->assertSame(['stripe:webhook', 'status'], $names);
    }

    // ---------------------------------------------------------------
    // Overwriting
    // ---------------------------------------------------------------

    public function testRegisterOverwritesPreviousEntry(): void
    {
        // Arrange
        $map = new CliRouteMap();
        $map->register('deploy', 'App\\OldDeploy');
        $map->register('deploy', 'App\\NewDeploy');

        // Act
        $route = $map->resolve('deploy');

        // Assert
        $this->assertSame('App\\NewDeploy', $route->dtoClass);
    }
}
