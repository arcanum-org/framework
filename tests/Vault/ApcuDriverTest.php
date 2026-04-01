<?php

declare(strict_types=1);

namespace Arcanum\Test\Vault;

use Arcanum\Vault\ApcuDriver;
use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ApcuDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class ApcuDriverTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('apcu') || !ini_get('apc.enable_cli')) {
            $this->markTestSkipped('APCu extension is not available or apc.enable_cli is disabled.');
        }

        apcu_clear_cache();
    }

    public function testSetGetRoundTrip(): void
    {
        $cache = new ApcuDriver();

        $cache->set('test-key', 'value');

        $this->assertSame('value', $cache->get('test-key'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $cache = new ApcuDriver();

        $this->assertSame('default', $cache->get('missing-key', 'default'));
    }

    public function testDeleteRemovesKey(): void
    {
        $cache = new ApcuDriver();

        $cache->set('test-key', 'value');
        $cache->delete('test-key');

        $this->assertNull($cache->get('test-key'));
    }

    public function testClearRemovesAllKeys(): void
    {
        $cache = new ApcuDriver();

        $cache->set('a', 1);
        $cache->set('b', 2);
        $cache->clear();

        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $cache = new ApcuDriver();

        $cache->set('test-key', 'value');

        $this->assertTrue($cache->has('test-key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $cache = new ApcuDriver();

        $this->assertFalse($cache->has('missing-key'));
    }

    public function testInvalidKeyThrows(): void
    {
        $cache = new ApcuDriver();

        $this->expectException(InvalidArgument::class);
        $cache->get('bad{key}');
    }

    public function testStoresComplexValues(): void
    {
        $cache = new ApcuDriver();

        $cache->set('array', ['nested' => true]);

        $this->assertSame(['nested' => true], $cache->get('array'));
    }
}
