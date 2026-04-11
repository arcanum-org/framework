<?php

declare(strict_types=1);

namespace Arcanum\Test\Vault;

use Arcanum\Vault\ArrayDriver;
use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use Arcanum\Vault\PrefixedCache;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(PrefixedCache::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class PrefixedCacheTest extends TestCase
{
    public function testGetSetPrependsPrefix(): void
    {
        $inner = new ArrayDriver();
        $cache = new PrefixedCache($inner, 'app.');

        $cache->set('key', 'value');

        $this->assertSame('value', $cache->get('key'));
        $this->assertSame('value', $inner->get('app.key'));
        $this->assertNull($inner->get('key'));
    }

    public function testDeletePrependsPrefix(): void
    {
        $inner = new ArrayDriver();
        $cache = new PrefixedCache($inner, 'app.');

        $cache->set('key', 'value');
        $cache->delete('key');

        $this->assertNull($cache->get('key'));
        $this->assertNull($inner->get('app.key'));
    }

    public function testHasPrependsPrefix(): void
    {
        $inner = new ArrayDriver();
        $cache = new PrefixedCache($inner, 'app.');

        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));
        $this->assertFalse($cache->has('other'));
    }

    public function testClearDelegatesToInner(): void
    {
        $inner = new ArrayDriver();
        $inner->set('other', 'data');
        $cache = new PrefixedCache($inner, 'app.');
        $cache->set('key', 'value');

        $cache->clear();

        $this->assertNull($cache->get('key'));
        $this->assertNull($inner->get('other'));
    }

    public function testGetMultiplePrependsPrefix(): void
    {
        $inner = new ArrayDriver();
        $cache = new PrefixedCache($inner, 'fw.');

        $cache->set('a', 1);
        $cache->set('b', 2);

        $result = $cache->getMultiple(['a', 'b', 'c'], 'default');

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 'default'], $result);
    }

    public function testSetMultiplePrependsPrefix(): void
    {
        $inner = new ArrayDriver();
        $cache = new PrefixedCache($inner, 'fw.');

        $cache->setMultiple(['a' => 1, 'b' => 2]);

        $this->assertSame(1, $inner->get('fw.a'));
        $this->assertSame(2, $inner->get('fw.b'));
    }

    public function testDeleteMultiplePrependsPrefix(): void
    {
        $inner = new ArrayDriver();
        $cache = new PrefixedCache($inner, 'fw.');

        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->deleteMultiple(['a']);

        $this->assertNull($cache->get('a'));
        $this->assertSame(2, $cache->get('b'));
    }

    public function testDifferentPrefixesIsolateData(): void
    {
        $inner = new ArrayDriver();
        $app = new PrefixedCache($inner, 'app.');
        $fw = new PrefixedCache($inner, 'fw.');

        $app->set('key', 'app-value');
        $fw->set('key', 'fw-value');

        $this->assertSame('app-value', $app->get('key'));
        $this->assertSame('fw-value', $fw->get('key'));
    }
}
