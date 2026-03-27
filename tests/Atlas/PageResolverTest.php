<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\PageResolver;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(PageResolver::class)]
#[UsesClass(Route::class)]
#[UsesClass(Strings::class)]
#[UsesClass(UnresolvableRoute::class)]
final class PageResolverTest extends TestCase
{
    // ---------------------------------------------------------------
    // Root path / → Index
    // ---------------------------------------------------------------

    public function testRootPathResolvesToIndex(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/');

        // Act
        $route = $pages->resolve('/');

        // Assert
        $this->assertSame('App\\Pages\\Index', $route->dtoClass);
    }

    public function testRootPathDefaultFormatIsHtml(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/');

        // Act
        $route = $pages->resolve('/');

        // Assert
        $this->assertSame('html', $route->format);
    }

    public function testRootPathIsAlwaysAQuery(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/');

        // Act
        $route = $pages->resolve('/');

        // Assert
        $this->assertTrue($route->isQuery());
        $this->assertSame('', $route->handlerPrefix);
    }

    // ---------------------------------------------------------------
    // Single-segment page
    // ---------------------------------------------------------------

    public function testSingleSegmentPage(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/thing');

        // Act
        $route = $pages->resolve('/thing');

        // Assert
        $this->assertSame('App\\Pages\\Thing', $route->dtoClass);
    }

    public function testSingleSegmentKebabCase(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/about-us');

        // Act
        $route = $pages->resolve('/about-us');

        // Assert
        $this->assertSame('App\\Pages\\AboutUs', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // Nested page
    // ---------------------------------------------------------------

    public function testNestedPage(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/docs/getting-started');

        // Act
        $route = $pages->resolve('/docs/getting-started');

        // Assert
        $this->assertSame('App\\Pages\\Docs\\GettingStarted', $route->dtoClass);
    }

    public function testDeeplyNestedPage(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/docs/guides/quick-start');

        // Act
        $route = $pages->resolve('/docs/guides/quick-start');

        // Assert
        $this->assertSame('App\\Pages\\Docs\\Guides\\QuickStart', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // Default format (html) and per-page format override
    // ---------------------------------------------------------------

    public function testDefaultFormatIsHtml(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/thing');

        // Act
        $route = $pages->resolve('/thing');

        // Assert
        $this->assertSame('html', $route->format);
    }

    public function testGlobalDefaultFormatIsConfigurable(): void
    {
        // Arrange
        $pages = new PageResolver(defaultFormat: 'json');
        $pages->register('/api-status');

        // Act
        $route = $pages->resolve('/api-status');

        // Assert
        $this->assertSame('json', $route->format);
    }

    public function testPerPageFormatOverride(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/api-status', format: 'json');

        // Act
        $route = $pages->resolve('/api-status');

        // Assert
        $this->assertSame('json', $route->format);
    }

    public function testPerPageFormatOverridesGlobalDefault(): void
    {
        // Arrange
        $pages = new PageResolver(defaultFormat: 'html');
        $pages->register('/feed', format: 'csv');

        // Act
        $route = $pages->resolve('/feed');

        // Assert
        $this->assertSame('csv', $route->format);
    }

    // ---------------------------------------------------------------
    // Context-aware format extension overrides page default
    // ---------------------------------------------------------------

    public function testExtensionFormatOverridesPageDefault(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/thing');

        // Act — simulate HttpRouter passing the parsed extension format
        $route = $pages->resolve('/thing', extensionFormat: 'json');

        // Assert
        $this->assertSame('json', $route->format);
    }

    public function testExtensionFormatOverridesPerPageFormat(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/thing', format: 'html');

        // Act
        $route = $pages->resolve('/thing', extensionFormat: 'csv');

        // Assert
        $this->assertSame('csv', $route->format);
    }

    public function testNoExtensionFallsBackToPageFormat(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/thing', format: 'json');

        // Act
        $route = $pages->resolve('/thing', extensionFormat: null);

        // Assert
        $this->assertSame('json', $route->format);
    }

    // ---------------------------------------------------------------
    // Unregistered page returns 404
    // ---------------------------------------------------------------

    public function testUnregisteredPathThrowsUnresolvableRoute(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/thing');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $pages->resolve('/unknown');
    }

    public function testEmptyRegistryThrowsForAnyPath(): void
    {
        // Arrange
        $pages = new PageResolver();

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $pages->resolve('/');
    }

    // ---------------------------------------------------------------
    // has() method
    // ---------------------------------------------------------------

    public function testHasReturnsTrueForRegisteredPage(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/thing');

        // Act & Assert
        $this->assertTrue($pages->has('/thing'));
    }

    public function testHasReturnsFalseForUnregisteredPage(): void
    {
        // Arrange
        $pages = new PageResolver();

        // Act & Assert
        $this->assertFalse($pages->has('/thing'));
    }

    public function testHasNormalizesSlashes(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('thing');

        // Act & Assert
        $this->assertTrue($pages->has('/thing'));
        $this->assertTrue($pages->has('thing'));
        $this->assertTrue($pages->has('/thing/'));
    }

    // ---------------------------------------------------------------
    // Configurable namespace
    // ---------------------------------------------------------------

    public function testCustomNamespace(): void
    {
        // Arrange
        $pages = new PageResolver(namespace: 'MyApp\\Views');
        $pages->register('/thing');

        // Act
        $route = $pages->resolve('/thing');

        // Assert
        $this->assertSame('MyApp\\Views\\Thing', $route->dtoClass);
    }

    public function testCustomNamespaceRootPath(): void
    {
        // Arrange
        $pages = new PageResolver(namespace: 'MyApp\\Views');
        $pages->register('/');

        // Act
        $route = $pages->resolve('/');

        // Assert
        $this->assertSame('MyApp\\Views\\Index', $route->dtoClass);
    }
}
