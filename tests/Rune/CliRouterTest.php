<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Rune\CliRouter;
use Arcanum\Rune\Input;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CliRouter::class)]
#[UsesClass(ConventionResolver::class)]
#[UsesClass(Input::class)]
#[UsesClass(Route::class)]
#[UsesClass(Strings::class)]
final class CliRouterTest extends TestCase
{
    private function router(): CliRouter
    {
        return new CliRouter(
            new ConventionResolver(rootNamespace: 'Arcanum\\Test\\Fixture'),
        );
    }

    // ---------------------------------------------------------------
    // Query routing
    // ---------------------------------------------------------------

    public function testResolvesQueryWithSingleSegment(): void
    {
        // Arrange — Arcanum\Test\Fixture\Shop\Query\Products exists
        $input = new Input('query:shop:products');

        // Act
        $route = $this->router()->resolve($input);

        // Assert
        $this->assertSame('Arcanum\\Test\\Fixture\\Shop\\Query\\Products', $route->dtoClass);
        $this->assertTrue($route->isQuery());
        $this->assertSame('', $route->handlerPrefix);
    }

    public function testResolvesQueryWithNestedPath(): void
    {
        // Arrange — Arcanum\Test\Fixture\Catalog\Query\Products\Featured exists
        $input = new Input('query:catalog:products:featured');

        // Act
        $route = $this->router()->resolve($input);

        // Assert
        $this->assertSame('Arcanum\\Test\\Fixture\\Catalog\\Query\\Products\\Featured', $route->dtoClass);
        $this->assertTrue($route->isQuery());
    }

    // ---------------------------------------------------------------
    // Command routing
    // ---------------------------------------------------------------

    public function testResolvesCommandWithDomainSegment(): void
    {
        // Arrange — Arcanum\Test\Fixture\Contact\Command\Submit exists
        $input = new Input('command:contact:submit');

        // Act
        $route = $this->router()->resolve($input);

        // Assert
        $this->assertSame('Arcanum\\Test\\Fixture\\Contact\\Command\\Submit', $route->dtoClass);
        $this->assertTrue($route->isCommand());
        $this->assertSame('', $route->handlerPrefix);
    }

    // ---------------------------------------------------------------
    // Handler-only routes
    // ---------------------------------------------------------------

    public function testResolvesWhenOnlyHandlerClassExists(): void
    {
        // Arrange — Arcanum\Test\Fixture\Widgets\Query\ListHandler exists but List does not
        $input = new Input('query:widgets:list');

        // Act
        $route = $this->router()->resolve($input);

        // Assert
        $this->assertSame('Arcanum\\Test\\Fixture\\Widgets\\Query\\List', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // Format handling
    // ---------------------------------------------------------------

    public function testDefaultFormatIsCli(): void
    {
        // Arrange
        $input = new Input('query:shop:products');

        // Act
        $route = $this->router()->resolve($input);

        // Assert
        $this->assertSame('cli', $route->format);
    }

    public function testFormatFlagOverridesDefault(): void
    {
        // Arrange
        $input = new Input(
            'query:shop:products',
            options: ['format' => 'json'],
        );

        // Act
        $route = $this->router()->resolve($input);

        // Assert
        $this->assertSame('json', $route->format);
    }

    public function testCustomDefaultFormat(): void
    {
        // Arrange
        $router = new CliRouter(
            new ConventionResolver(rootNamespace: 'Arcanum\\Test\\Fixture'),
            defaultFormat: 'table',
        );
        $input = new Input('query:shop:products');

        // Act
        $route = $router->resolve($input);

        // Assert
        $this->assertSame('table', $route->format);
    }

    // ---------------------------------------------------------------
    // Error cases
    // ---------------------------------------------------------------

    public function testThrowsForNonInputObject(): void
    {
        // Arrange
        $router = $this->router();

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('CliRouter expects an Input');
        $router->resolve(new \stdClass());
    }

    public function testThrowsForEmptyCommand(): void
    {
        // Arrange
        $input = new Input('');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('No command specified');
        $this->router()->resolve($input);
    }

    public function testThrowsForCommandWithNoPrefix(): void
    {
        // Arrange
        $input = new Input('health');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('no type prefix');
        $this->router()->resolve($input);
    }

    public function testThrowsForUnknownPrefix(): void
    {
        // Arrange
        $input = new Input('event:something');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('Unknown type prefix "event"');
        $this->router()->resolve($input);
    }

    public function testThrowsForEmptyNameAfterPrefix(): void
    {
        // Arrange
        $input = new Input('command:');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('No command name after "command:" prefix');
        $this->router()->resolve($input);
    }

    public function testThrowsForNonExistentClass(): void
    {
        // Arrange
        $input = new Input('query:nonexistent:thing');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('No query found for "query:nonexistent:thing"');
        $this->router()->resolve($input);
    }

    public function testThrowsForNonExistentCommand(): void
    {
        // Arrange
        $input = new Input('command:nonexistent:action');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('No command found for "command:nonexistent:action"');
        $this->router()->resolve($input);
    }

    // ---------------------------------------------------------------
    // Prefix case insensitivity
    // ---------------------------------------------------------------

    public function testPrefixIsCaseInsensitive(): void
    {
        // Arrange
        $input = new Input('Query:shop:products');

        // Act
        $route = $this->router()->resolve($input);

        // Assert
        $this->assertSame('Arcanum\\Test\\Fixture\\Shop\\Query\\Products', $route->dtoClass);
    }

    public function testCommandPrefixIsCaseInsensitive(): void
    {
        // Arrange
        $input = new Input('COMMAND:contact:submit');

        // Act
        $route = $this->router()->resolve($input);

        // Assert
        $this->assertSame('Arcanum\\Test\\Fixture\\Contact\\Command\\Submit', $route->dtoClass);
    }
}
