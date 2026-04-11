<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\MiddlewareDiscovery;
use Arcanum\Atlas\RouteMiddleware;
use Arcanum\Vault\ArrayDriver;
use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MiddlewareDiscovery::class)]
#[UsesClass(RouteMiddleware::class)]
#[UsesClass(\Arcanum\Atlas\Attribute\HttpMiddleware::class)]
#[UsesClass(\Arcanum\Atlas\Attribute\Before::class)]
#[UsesClass(\Arcanum\Atlas\Attribute\After::class)]
#[UsesClass(\Arcanum\Parchment\Searcher::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class MiddlewareDiscoveryTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/Fixture/Middleware';
    private const ROOT_NS = 'Arcanum\\Test\\Atlas\\Fixture\\Middleware';

    public function testDiscoverReturnsEmptyForNonExistentDirectory(): void
    {
        // Arrange
        $discovery = new MiddlewareDiscovery(
            rootNamespace: self::ROOT_NS,
            rootDirectory: '/nonexistent/path',
        );

        // Act
        $result = $discovery->discover();

        // Assert
        $this->assertSame([], $result);
    }

    public function testDiscoverFindsDtoWithAttributeMiddleware(): void
    {
        // Arrange
        $discovery = new MiddlewareDiscovery(
            rootNamespace: self::ROOT_NS,
            rootDirectory: self::FIXTURE_DIR,
        );

        // Act
        $result = $discovery->discover();

        // Assert — BanUser has both directory and attribute middleware
        $banUser = self::ROOT_NS . '\\Admin\\Command\\BanUser';
        $this->assertArrayHasKey($banUser, $result);

        $mw = $result[$banUser];
        // HTTP: Root → Admin → BanUser attribute
        $this->assertSame(['RootHttpMiddleware', 'AdminHttpMiddleware', 'BanUserHttpMiddleware'], $mw->http);
        // Before: Admin → BanUser attribute
        $this->assertSame(['AdminBeforeMiddleware', 'BanUserBeforeMiddleware'], $mw->before);
    }

    public function testDiscoverFindsDtoWithDirectoryOnlyMiddleware(): void
    {
        // Arrange
        $discovery = new MiddlewareDiscovery(
            rootNamespace: self::ROOT_NS,
            rootDirectory: self::FIXTURE_DIR,
        );

        // Act
        $result = $discovery->discover();

        // Assert — AuditLog has directory + its own Before attribute
        $auditLog = self::ROOT_NS . '\\Admin\\Query\\AuditLog';
        $this->assertArrayHasKey($auditLog, $result);

        $mw = $result[$auditLog];
        // HTTP: Root + Admin directory
        $this->assertSame(['RootHttpMiddleware', 'AdminHttpMiddleware'], $mw->http);
        // Before: Admin directory + AuditLog attribute
        $this->assertSame(['AdminBeforeMiddleware', 'AuditLogBeforeMiddleware'], $mw->before);
    }

    public function testDiscoverFindsRootDirectoryOnlyMiddleware(): void
    {
        // Arrange
        $discovery = new MiddlewareDiscovery(
            rootNamespace: self::ROOT_NS,
            rootDirectory: self::FIXTURE_DIR,
        );

        // Act
        $result = $discovery->discover();

        // Assert — Status has only root directory middleware (no Admin, no attributes)
        $status = self::ROOT_NS . '\\Public\\Query\\Status';
        $this->assertArrayHasKey($status, $result);

        $mw = $result[$status];
        $this->assertSame(['RootHttpMiddleware'], $mw->http);
        $this->assertSame([], $mw->before);
        $this->assertSame([], $mw->after);
    }

    public function testDiscoveryCacheWriteAndRead(): void
    {
        // Arrange
        $cache = new ArrayDriver();

        $discovery = new MiddlewareDiscovery(
            rootNamespace: self::ROOT_NS,
            rootDirectory: self::FIXTURE_DIR,
            cache: $cache,
        );

        // Act — first call scans and writes cache
        $first = $discovery->discover();

        // Assert cache is populated
        $this->assertTrue($cache->has('middleware_discovery'));

        // Act — second call reads from cache
        $second = $discovery->discover();

        // Assert — same results
        $this->assertEquals($first, $second);
    }

    public function testClearCache(): void
    {
        // Arrange
        $cache = new ArrayDriver();

        $discovery = new MiddlewareDiscovery(
            rootNamespace: self::ROOT_NS,
            rootDirectory: self::FIXTURE_DIR,
            cache: $cache,
        );

        $discovery->discover(); // writes cache
        $this->assertTrue($cache->has('middleware_discovery'));

        // Act
        $discovery->clearCache();

        // Assert
        $this->assertFalse($cache->has('middleware_discovery'));
    }

    public function testDirectoryMiddlewareOrderIsShallowFirst(): void
    {
        // Arrange
        $discovery = new MiddlewareDiscovery(
            rootNamespace: self::ROOT_NS,
            rootDirectory: self::FIXTURE_DIR,
        );

        // Act
        $result = $discovery->discover();

        // Assert — BanUser gets Root (shallow) before Admin (deeper)
        $banUser = self::ROOT_NS . '\\Admin\\Command\\BanUser';
        $mw = $result[$banUser];

        // Root is listed before Admin in http
        $rootIndex = array_search('RootHttpMiddleware', $mw->http, true);
        $adminIndex = array_search('AdminHttpMiddleware', $mw->http, true);
        $this->assertLessThan($adminIndex, $rootIndex);
    }
}
