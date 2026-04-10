<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\PageDiscovery;
use Arcanum\Atlas\RouteMap;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\MethodNotAllowed;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Arcanum\Toolkit\Strings;
use Arcanum\Vault\ArrayDriver;
use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(PageDiscovery::class)]
#[UsesClass(RouteMap::class)]
#[UsesClass(Route::class)]
#[UsesClass(MethodNotAllowed::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Strings::class)]
#[UsesClass(UnresolvableRoute::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class PageDiscoveryTest extends TestCase
{
    private const PAGES_NS = 'Arcanum\\Test\\Atlas\\Fixture\\Pages';
    private const PAGES_DIR = __DIR__ . '/Fixture/Pages';

    // -----------------------------------------------------------
    // Discovery
    // -----------------------------------------------------------

    public function testDiscoversIndexAsRootPath(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);

        // Act
        $pages = $discovery->discover();

        // Assert
        $this->assertArrayHasKey('/', $pages);
        $this->assertSame(self::PAGES_NS . '\\Index', $pages['/']);
    }

    public function testDiscoversPascalCaseAsKebabPath(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);

        // Act
        $pages = $discovery->discover();

        // Assert
        $this->assertArrayHasKey('/about-us', $pages);
        $this->assertSame(self::PAGES_NS . '\\AboutUs', $pages['/about-us']);
    }

    public function testDiscoversNestedDirectories(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);

        // Act
        $pages = $discovery->discover();

        // Assert
        $this->assertArrayHasKey('/docs/getting-started', $pages);
        $this->assertSame(
            self::PAGES_NS . '\\Docs\\GettingStarted',
            $pages['/docs/getting-started'],
        );
    }

    public function testPhpOnlyFilesAreNotDiscovered(): void
    {
        // Arrange — IndexHandler.php exists but no IndexHandler.html
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);

        // Act
        $pages = $discovery->discover();

        // Assert — handler PHP files are naturally excluded (only .html is scanned)
        foreach ($pages as $class) {
            $this->assertStringNotContainsString('Handler', $class);
        }
    }

    public function testUnderscorePrefixedFilesAreNotDiscovered(): void
    {
        // Arrange — _Partial.html exists in the fixture Pages directory
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);

        // Act
        $pages = $discovery->discover();

        // Assert — no path or class containing "Partial"
        foreach ($pages as $path => $class) {
            $this->assertStringNotContainsString('Partial', $path);
            $this->assertStringNotContainsString('Partial', $class);
        }
    }

    public function testUnderscorePrefixedFilesInSubdirectoriesAreNotDiscovered(): void
    {
        // Arrange — Docs/_Sidebar.html exists in the fixture Pages directory
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);

        // Act
        $pages = $discovery->discover();

        // Assert — no path or class containing "Sidebar"
        foreach ($pages as $path => $class) {
            $this->assertStringNotContainsString('Sidebar', $path);
            $this->assertStringNotContainsString('Sidebar', $class);
        }
    }

    public function testReturnsEmptyForNonExistentDirectory(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, '/nonexistent/directory');

        // Act
        $pages = $discovery->discover();

        // Assert
        $this->assertSame([], $pages);
    }

    // -----------------------------------------------------------
    // Registration in RouteMap
    // -----------------------------------------------------------

    public function testRegistersDiscoveredPagesAsGetOnlyRoutes(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);
        $routeMap = new RouteMap();

        // Act
        $discovery->register($routeMap);

        // Assert — root page resolves
        $route = $routeMap->resolve('/', 'GET');
        $this->assertSame(self::PAGES_NS . '\\Index', $route->dtoClass);
        $this->assertSame('html', $route->format);
    }

    public function testRegisteredPagesRejectNonGetMethods(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);
        $routeMap = new RouteMap();
        $discovery->register($routeMap);

        // Assert
        $this->expectException(MethodNotAllowed::class);
        $routeMap->resolve('/', 'POST');
    }

    public function testFormatOverrideApplied(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);
        $routeMap = new RouteMap();

        // Act — override root page format to json
        $discovery->register($routeMap, ['/' => 'json']);

        // Assert
        $route = $routeMap->resolve('/', 'GET');
        $this->assertSame('json', $route->format);
    }

    public function testDefaultFormatIsHtml(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);
        $routeMap = new RouteMap();
        $discovery->register($routeMap);

        // Act
        $route = $routeMap->resolve('/about-us', 'GET');

        // Assert
        $this->assertSame('html', $route->format);
    }

    public function testRegisteredPagesHaveIsPageFlag(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);
        $routeMap = new RouteMap();
        $discovery->register($routeMap);

        // Act
        $route = $routeMap->resolve('/', 'GET');

        // Assert
        $this->assertTrue($route->isPage());
    }

    public function testCustomDefaultFormat(): void
    {
        // Arrange
        $discovery = new PageDiscovery(
            self::PAGES_NS,
            self::PAGES_DIR,
            defaultFormat: 'json',
        );
        $routeMap = new RouteMap();
        $discovery->register($routeMap);

        // Act
        $route = $routeMap->resolve('/about-us', 'GET');

        // Assert
        $this->assertSame('json', $route->format);
    }

    // -----------------------------------------------------------
    // Caching
    // -----------------------------------------------------------

    public function testCachesDiscoveryResults(): void
    {
        // Arrange
        $cache = new ArrayDriver();
        $discovery = new PageDiscovery(
            self::PAGES_NS,
            self::PAGES_DIR,
            cache: $cache,
        );

        // Act — first call scans and writes cache
        $pages = $discovery->discover();

        // Assert — cache is populated
        $this->assertTrue($cache->has('page_discovery'));

        // Act — second call reads from cache
        $cachedPages = $discovery->discover();
        $this->assertSame($pages, $cachedPages);
    }

    public function testNoCachingWhenCacheIsNull(): void
    {
        // Arrange — null cache means no caching
        $discovery = new PageDiscovery(
            self::PAGES_NS,
            self::PAGES_DIR,
            cache: null,
        );

        // Act — should scan every time
        $pages = $discovery->discover();

        // Assert — still works
        $this->assertArrayHasKey('/', $pages);
    }

    public function testClearCacheRemovesEntry(): void
    {
        // Arrange
        $cache = new ArrayDriver();
        $discovery = new PageDiscovery(
            self::PAGES_NS,
            self::PAGES_DIR,
            cache: $cache,
        );
        $discovery->discover(); // populate cache

        // Act
        $discovery->clearCache();

        // Assert
        $this->assertFalse($cache->has('page_discovery'));
    }

    public function testClearCacheWithNullCacheDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();

        // Arrange
        $discovery = new PageDiscovery(
            self::PAGES_NS,
            self::PAGES_DIR,
            cache: null,
        );

        // Act — should not throw
        $discovery->clearCache();
    }
}
