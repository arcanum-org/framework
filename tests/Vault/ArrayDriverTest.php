<?php

declare(strict_types=1);

namespace Arcanum\Test\Vault;

use Arcanum\Vault\ArrayDriver;
use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class ArrayDriverTest extends TestCase
{
    public function testSetGetRoundTrip(): void
    {
        $cache = new ArrayDriver();

        $cache->set('key', 'value');

        $this->assertSame('value', $cache->get('key'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $cache = new ArrayDriver();

        $this->assertSame('default', $cache->get('missing', 'default'));
    }

    public function testGetReturnsNullDefaultByDefault(): void
    {
        $cache = new ArrayDriver();

        $this->assertNull($cache->get('missing'));
    }

    public function testTtlExpiryReturnsDefault(): void
    {
        $cache = new ArrayDriver();

        $cache->set('key', 'value', -1);

        $this->assertNull($cache->get('key'));
    }

    public function testDeleteRemovesKey(): void
    {
        $cache = new ArrayDriver();

        $cache->set('key', 'value');
        $cache->delete('key');

        $this->assertNull($cache->get('key'));
    }

    public function testClearRemovesAllKeys(): void
    {
        $cache = new ArrayDriver();

        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->clear();

        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $cache = new ArrayDriver();

        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $cache = new ArrayDriver();

        $this->assertFalse($cache->has('missing'));
    }

    public function testHasReturnsTrueForNullValue(): void
    {
        $cache = new ArrayDriver();

        $cache->set('key', null);

        $this->assertTrue($cache->has('key'));
    }

    public function testGetMultiple(): void
    {
        $cache = new ArrayDriver();

        $cache->set('a', 1);
        $cache->set('b', 2);

        $result = $cache->getMultiple(['a', 'b', 'c'], 'default');

        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 'default'], $result);
    }

    public function testSetMultiple(): void
    {
        $cache = new ArrayDriver();

        $cache->setMultiple(['a' => 1, 'b' => 2]);

        $this->assertSame(1, $cache->get('a'));
        $this->assertSame(2, $cache->get('b'));
    }

    public function testDeleteMultiple(): void
    {
        $cache = new ArrayDriver();

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
        $cache = new ArrayDriver();

        $this->expectException(InvalidArgument::class);
        $cache->get('bad{key}');
    }

    public function testNewInstanceIsEmpty(): void
    {
        $cache1 = new ArrayDriver();
        $cache1->set('key', 'value');

        $cache2 = new ArrayDriver();

        $this->assertNull($cache2->get('key'));
    }

    public function testDateIntervalTtl(): void
    {
        $cache = new ArrayDriver();

        $cache->set('key', 'value', new \DateInterval('PT3600S'));

        $this->assertSame('value', $cache->get('key'));
    }

    public function testStoresComplexValues(): void
    {
        $cache = new ArrayDriver();

        $cache->set('array', ['nested' => ['data' => true]]);
        $cache->set('int', 42);
        $cache->set('null', null);

        $this->assertSame(['nested' => ['data' => true]], $cache->get('array'));
        $this->assertSame(42, $cache->get('int'));
        $this->assertNull($cache->get('null'));
    }
}
