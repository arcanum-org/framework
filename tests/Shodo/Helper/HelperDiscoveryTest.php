<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Helper;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Shodo\Helper\HelperDiscovery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(HelperDiscovery::class)]
#[UsesClass(Reader::class)]
#[UsesClass(FileSystem::class)]
final class HelperDiscoveryTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/arcanum_helper_discovery_' . uniqid();
        mkdir($this->rootDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function writeHelpers(string $relativePath, string $content): void
    {
        $dir = $this->rootDir . '/' . $relativePath;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/Helpers.php', $content);
    }

    public function testDiscoversRootLevelHelpers(): void
    {
        // Arrange
        $this->writeHelpers('', "<?php\nreturn ['Format' => 'App\\\\Helper\\\\FormatHelper'];");
        $discovery = new HelperDiscovery('App\\Domain', $this->rootDir);

        // Act
        $result = $discovery->discover();

        // Assert
        $this->assertSame([
            'App\\Domain' => ['Format' => 'App\\Helper\\FormatHelper'],
        ], $result);
    }

    public function testDiscoversDomainScopedHelpers(): void
    {
        // Arrange
        $this->writeHelpers('Shop', "<?php\nreturn ['Cart' => 'App\\\\Domain\\\\Shop\\\\CartHelper'];");
        $discovery = new HelperDiscovery('App\\Domain', $this->rootDir);

        // Act
        $result = $discovery->discover();

        // Assert
        $this->assertSame([
            'App\\Domain\\Shop' => ['Cart' => 'App\\Domain\\Shop\\CartHelper'],
        ], $result);
    }

    public function testDiscoversMultipleLevels(): void
    {
        // Arrange
        $this->writeHelpers('', "<?php\nreturn ['Format' => 'FormatHelper'];");
        $this->writeHelpers('Shop', "<?php\nreturn ['Cart' => 'CartHelper'];");
        $this->writeHelpers('Shop/Checkout', "<?php\nreturn ['Payment' => 'PaymentHelper'];");
        $discovery = new HelperDiscovery('App\\Domain', $this->rootDir);

        // Act
        $result = $discovery->discover();

        // Assert
        $this->assertArrayHasKey('App\\Domain', $result);
        $this->assertArrayHasKey('App\\Domain\\Shop', $result);
        $this->assertArrayHasKey('App\\Domain\\Shop\\Checkout', $result);
        $this->assertSame(['Format' => 'FormatHelper'], $result['App\\Domain']);
        $this->assertSame(['Cart' => 'CartHelper'], $result['App\\Domain\\Shop']);
        $this->assertSame(['Payment' => 'PaymentHelper'], $result['App\\Domain\\Shop\\Checkout']);
    }

    public function testMissingDirectoryReturnsEmpty(): void
    {
        // Arrange
        $discovery = new HelperDiscovery('App\\Domain', '/nonexistent/path');

        // Act
        $result = $discovery->discover();

        // Assert
        $this->assertSame([], $result);
    }

    public function testEmptyDirectoryReturnsEmpty(): void
    {
        // Arrange — rootDir exists but has no Helpers.php files
        $discovery = new HelperDiscovery('App\\Domain', $this->rootDir);

        // Act
        $result = $discovery->discover();

        // Assert
        $this->assertSame([], $result);
    }

    public function testCachesDiscoveryResults(): void
    {
        // Arrange
        $this->writeHelpers('', "<?php\nreturn ['Format' => 'FormatHelper'];");
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('get')->with('helper_discovery')->willReturn(null);
        $cache->expects($this->once())->method('set')->with(
            'helper_discovery',
            ['App\\Domain' => ['Format' => 'FormatHelper']],
            null,
        );
        $discovery = new HelperDiscovery('App\\Domain', $this->rootDir, cache: $cache);

        // Act
        $discovery->discover();
    }

    public function testReturnsCachedResultsWithoutScanning(): void
    {
        // Arrange — cache returns a result, so no filesystem scanning needed
        $cached = ['App\\Domain' => ['Format' => 'FormatHelper']];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn($cached);
        $discovery = new HelperDiscovery('App\\Domain', '/nonexistent', cache: $cache);

        // Act
        $result = $discovery->discover();

        // Assert
        $this->assertSame($cached, $result);
    }

    public function testClearCacheDeletesEntry(): void
    {
        // Arrange
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete')->with('helper_discovery');
        $discovery = new HelperDiscovery('App\\Domain', $this->rootDir, cache: $cache);

        // Act
        $discovery->clearCache();
    }
}
