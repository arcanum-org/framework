<?php

declare(strict_types=1);

namespace Arcanum\Test\Vault;

use Arcanum\Hourglass\FrozenClock;
use Arcanum\Hourglass\SystemClock;
use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Vault\FileDriver;
use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(FileDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
#[UsesClass(\Arcanum\Parchment\Writer::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
#[UsesClass(\Arcanum\Parchment\Searcher::class)]
#[UsesClass(FrozenClock::class)]
#[UsesClass(SystemClock::class)]
final class FileDriverTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/arcanum_cache_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir($this->cacheDir);
        }
    }

    public function testSetGetRoundTrip(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('key', 'value');

        $this->assertSame('value', $cache->get('key'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $this->assertSame('default', $cache->get('missing', 'default'));
    }

    public function testGetReturnsNullDefaultByDefault(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $this->assertNull($cache->get('missing'));
    }

    public function testTtlExpiryReturnsDefault(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('key', 'value', -1);

        $this->assertNull($cache->get('key'));
    }

    public function testDeleteRemovesKey(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('key', 'value');
        $cache->delete('key');

        $this->assertNull($cache->get('key'));
    }

    public function testClearRemovesAllKeys(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->clear();

        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $this->assertFalse($cache->has('missing'));
    }

    public function testGetMultiple(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('a', 1);
        $cache->set('b', 2);

        $result = $cache->getMultiple(['a', 'b', 'c'], 'default');

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 'default'], $result);
    }

    public function testSetMultiple(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->setMultiple(['a' => 1, 'b' => 2]);

        $this->assertSame(1, $cache->get('a'));
        $this->assertSame(2, $cache->get('b'));
    }

    public function testDeleteMultiple(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->set('c', 3);
        $cache->deleteMultiple(['a', 'c']);

        $this->assertNull($cache->get('a'));
        $this->assertSame(2, $cache->get('b'));
        $this->assertNull($cache->get('c'));
    }

    public function testInvalidKeyThrows(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $this->expectException(InvalidArgument::class);
        $cache->get('bad{key}');
    }

    public function testCreatesDirectoryAutomatically(): void
    {
        $dir = $this->cacheDir . '/nested/deep';
        $cache = new FileDriver($dir);

        $cache->set('key', 'value');

        $this->assertSame('value', $cache->get('key'));

        // Cleanup nested dirs
        @unlink($dir . '/' . md5('key') . '.cache');
        @rmdir($dir);
        @rmdir($this->cacheDir . '/nested');
    }

    public function testStoresComplexValues(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('array', ['nested' => ['data' => true]]);
        $cache->set('int', 42);

        $this->assertSame(['nested' => ['data' => true]], $cache->get('array'));
        $this->assertSame(42, $cache->get('int'));
    }

    public function testDateIntervalTtl(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('key', 'value', new \DateInterval('PT3600S'));

        $this->assertSame('value', $cache->get('key'));
    }

    public function testHasReturnsTrueForNullValue(): void
    {
        $cache = new FileDriver($this->cacheDir);

        $cache->set('key', null);

        $this->assertTrue($cache->has('key'));
    }

    public function testEntryIsValidBeforeFrozenClockReachesExpiry(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-04-08 12:00:00'));
        $cache = new FileDriver(
            $this->cacheDir,
            new Reader(),
            new Writer(),
            new FileSystem(),
            $clock,
        );

        $cache->set('key', 'value', 60);
        $clock->advance(new \DateInterval('PT30S'));

        $this->assertSame('value', $cache->get('key'));
    }

    public function testEntryExpiresWhenFrozenClockAdvancesPastTtl(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-04-08 12:00:00'));
        $cache = new FileDriver(
            $this->cacheDir,
            new Reader(),
            new Writer(),
            new FileSystem(),
            $clock,
        );

        $cache->set('key', 'value', 60);
        $clock->advance(new \DateInterval('PT61S'));

        $this->assertNull($cache->get('key'));
    }

    public function testDateIntervalTtlExpiresWhenFrozenClockAdvancesPast(): void
    {
        $clock = new FrozenClock(new \DateTimeImmutable('2026-04-08 12:00:00'));
        $cache = new FileDriver(
            $this->cacheDir,
            new Reader(),
            new Writer(),
            new FileSystem(),
            $clock,
        );

        $cache->set('key', 'value', new \DateInterval('PT5M'));

        $clock->advance(new \DateInterval('PT4M'));
        $this->assertSame('value', $cache->get('key'));

        $clock->advance(new \DateInterval('PT2M'));
        $this->assertNull($cache->get('key'));
    }
}
