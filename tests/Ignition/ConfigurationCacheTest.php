<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition;

use Arcanum\Ignition\ConfigurationCache;
use Arcanum\Gather\Configuration;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Parchment\FileSystem;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Gather\Registry;

#[CoversClass(ConfigurationCache::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(Registry::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(FileSystem::class)]
final class ConfigurationCacheTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_config_cache_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testExistsReturnsFalseWhenNoCacheFile(): void
    {
        // Arrange
        $cache = new ConfigurationCache($this->tempDir . '/config.php');

        // Assert
        $this->assertFalse($cache->exists());
    }

    public function testWriteCreatesValidCacheFile(): void
    {
        // Arrange
        $cache = new ConfigurationCache($this->tempDir . '/config.php');
        $config = new Configuration([
            'app' => ['name' => 'Arcanum', 'debug' => true],
            'log' => ['level' => 'info'],
        ]);

        // Act
        $cache->write($config);

        // Assert
        $this->assertTrue($cache->exists());
    }

    public function testLoadReturnsCachedArray(): void
    {
        // Arrange
        $data = [
            'app' => ['name' => 'Arcanum', 'debug' => true],
            'log' => ['level' => 'info'],
        ];
        $cache = new ConfigurationCache($this->tempDir . '/config.php');
        $config = new Configuration($data);

        // Act
        $cache->write($config);
        $loaded = $cache->load();

        // Assert
        $this->assertSame($data, $loaded);
    }

    public function testClearDeletesCacheFile(): void
    {
        // Arrange
        $cache = new ConfigurationCache($this->tempDir . '/config.php');
        $cache->write(new Configuration(['app' => ['name' => 'Test']]));
        $this->assertTrue($cache->exists());

        // Act
        $cache->clear();

        // Assert
        $this->assertFalse($cache->exists());
    }

    public function testClearDoesNothingWhenNoCacheFile(): void
    {
        // Arrange
        $cache = new ConfigurationCache($this->tempDir . '/config.php');

        // Act — should not throw
        $cache->clear();

        // Assert
        $this->assertFalse($cache->exists());
    }

    public function testWriteCreatesDirectoryIfNotExists(): void
    {
        // Arrange
        $nestedPath = $this->tempDir . '/nested/dir/config.php';
        $cache = new ConfigurationCache($nestedPath);

        // Act
        $cache->write(new Configuration(['key' => 'value']));

        // Assert
        $this->assertTrue($cache->exists());

        // Clean up nested dirs
        unlink($nestedPath);
        rmdir($this->tempDir . '/nested/dir');
        rmdir($this->tempDir . '/nested');
    }

    public function testPathReturnsCachePath(): void
    {
        // Arrange
        $path = $this->tempDir . '/config.php';
        $cache = new ConfigurationCache($path);

        // Assert
        $this->assertSame($path, $cache->path());
    }

    public function testRoundTripPreservesAllDataTypes(): void
    {
        // Arrange
        $data = [
            'string' => 'hello',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'nested' => ['a' => ['b' => 'c']],
        ];
        $cache = new ConfigurationCache($this->tempDir . '/config.php');

        // Act
        $cache->write(new Configuration($data));
        $loaded = $cache->load();

        // Assert
        $this->assertSame($data, $loaded);
    }
}
