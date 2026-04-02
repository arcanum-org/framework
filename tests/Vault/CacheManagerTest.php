<?php

declare(strict_types=1);

namespace Arcanum\Test\Vault;

use Arcanum\Vault\ArrayDriver;
use Arcanum\Vault\CacheManager;
use Arcanum\Vault\FileDriver;
use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use Arcanum\Vault\NullDriver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CacheManager::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(FileDriver::class)]
#[UsesClass(NullDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
#[UsesClass(\Arcanum\Parchment\Writer::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
#[UsesClass(\Arcanum\Parchment\Searcher::class)]
#[UsesClass(\Arcanum\Gather\Configuration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
final class CacheManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_cm_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->tempDir);
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = glob($dir . '/*') ?: [];
        foreach ($items as $item) {
            is_dir($item) ? $this->cleanDir($item) : @unlink($item);
        }
        @rmdir($dir);
    }

    public function testResolvesDefaultStore(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: ['array' => ['driver' => 'array']],
        );

        $store = $manager->store();

        $this->assertInstanceOf(ArrayDriver::class, $store);
    }

    public function testResolvesNamedStore(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: [
                'array' => ['driver' => 'array'],
                'null' => ['driver' => 'null'],
            ],
        );

        $this->assertInstanceOf(NullDriver::class, $manager->store('null'));
    }

    public function testResolvesFileStore(): void
    {
        $manager = new CacheManager(
            defaultStore: 'file',
            stores: ['file' => ['driver' => 'file', 'path' => $this->tempDir . '/cache']],
        );

        $store = $manager->store('file');

        $this->assertInstanceOf(FileDriver::class, $store);
    }

    public function testThrowsOnUnknownStore(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: ['array' => ['driver' => 'array']],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cache store "nonexistent" is not configured');
        $manager->store('nonexistent');
    }

    public function testLazilyInstantiates(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: ['array' => ['driver' => 'array']],
        );

        $store1 = $manager->store('array');
        $store2 = $manager->store('array');

        $this->assertSame($store1, $store2);
    }

    public function testFrameworkStoreResolvesMapping(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: [
                'array' => ['driver' => 'array'],
                'null' => ['driver' => 'null'],
            ],
            frameworkStores: ['pages' => 'null'],
        );

        $this->assertInstanceOf(NullDriver::class, $manager->frameworkStore('pages'));
    }

    public function testFrameworkStoreFallsBackToDefault(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: ['array' => ['driver' => 'array']],
            frameworkStores: [],
        );

        $this->assertInstanceOf(ArrayDriver::class, $manager->frameworkStore('pages'));
    }

    public function testStoreNamesReturnsAllConfigured(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: [
                'array' => ['driver' => 'array'],
                'null' => ['driver' => 'null'],
            ],
        );

        $this->assertSame(['array', 'null'], $manager->storeNames());
    }

    public function testRelativeFilePathPrependsFilesDirectory(): void
    {
        $manager = new CacheManager(
            defaultStore: 'file',
            stores: ['file' => ['driver' => 'file', 'path' => 'cache/app']],
            filesDirectory: $this->tempDir,
        );

        $store = $manager->store('file');
        $store->set('test', 'value');

        $this->assertSame('value', $store->get('test'));
    }

    public function testThrowsOnUnknownDriver(): void
    {
        $manager = new CacheManager(
            defaultStore: 'bad',
            stores: ['bad' => ['driver' => 'unknown']],
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown cache driver "unknown"');
        $manager->store();
    }
}
