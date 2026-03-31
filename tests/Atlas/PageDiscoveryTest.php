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

    public function testSkipsHandlerClasses(): void
    {
        // Arrange
        $discovery = new PageDiscovery(self::PAGES_NS, self::PAGES_DIR);

        // Act
        $pages = $discovery->discover();

        // Assert — IndexHandler should not be a page
        foreach ($pages as $class) {
            $this->assertStringNotContainsString('Handler', $class);
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
        $cachePath = sys_get_temp_dir() . '/arcanum_pages_test_' . uniqid() . '.php';
        $discovery = new PageDiscovery(
            self::PAGES_NS,
            self::PAGES_DIR,
            cachePath: $cachePath,
        );

        // Act — first call scans and writes cache
        $pages = $discovery->discover();

        // Assert — cache file exists
        $this->assertFileExists($cachePath);

        // Act — second call reads from cache
        $cachedPages = $discovery->discover();
        $this->assertSame($pages, $cachedPages);

        // Cleanup
        @unlink($cachePath);
    }

    public function testClearCacheRemovesFile(): void
    {
        // Arrange
        $cachePath = sys_get_temp_dir() . '/arcanum_pages_test_' . uniqid() . '.php';
        $discovery = new PageDiscovery(
            self::PAGES_NS,
            self::PAGES_DIR,
            cachePath: $cachePath,
        );
        $discovery->discover(); // create cache

        // Act
        $discovery->clearCache();

        // Assert
        $this->assertFileDoesNotExist($cachePath);
    }
}
