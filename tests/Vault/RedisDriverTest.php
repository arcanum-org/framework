<?php

declare(strict_types=1);

namespace Arcanum\Test\Vault;

use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use Arcanum\Vault\RedisDriver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(RedisDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class RedisDriverTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('phpredis extension is not available.');
        }
    }

    private function makeMockRedis(): \Redis
    {
        return $this->createMock(\Redis::class);
    }

    public function testGetReturnsCachedValue(): void
    {
        $redis = $this->makeMockRedis();
        $redis->method('get')->with('key')->willReturn(serialize('value'));

        $cache = new RedisDriver($redis);

        $this->assertSame('value', $cache->get('key'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $redis = $this->makeMockRedis();
        $redis->method('get')->with('key')->willReturn(false);

        $cache = new RedisDriver($redis);

        $this->assertSame('default', $cache->get('key', 'default'));
    }

    public function testSetCallsRedisSet(): void
    {
        $redis = $this->makeMockRedis();
        $redis->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'))
            ->willReturn(true);

        $cache = new RedisDriver($redis);
        $cache->set('key', 'value');
    }

    public function testSetWithTtlCallsSetex(): void
    {
        $redis = $this->makeMockRedis();
        $redis->expects($this->once())
            ->method('setex')
            ->with('key', 3600, serialize('value'))
            ->willReturn(true);

        $cache = new RedisDriver($redis);
        $cache->set('key', 'value', 3600);
    }

    public function testDeleteCallsDel(): void
    {
        $redis = $this->makeMockRedis();
        $redis->expects($this->once())
            ->method('del')
            ->with('key');

        $cache = new RedisDriver($redis);
        $cache->delete('key');
    }

    public function testClearCallsFlushDb(): void
    {
        $redis = $this->makeMockRedis();
        $redis->expects($this->once())
            ->method('flushDB')
            ->willReturn(true);

        $cache = new RedisDriver($redis);
        $cache->clear();
    }

    public function testHasChecksExists(): void
    {
        $redis = $this->makeMockRedis();
        $redis->method('exists')->with('key')->willReturn(1);

        $cache = new RedisDriver($redis);

        $this->assertTrue($cache->has('key'));
    }

    public function testHasReturnsFalseWhenNotExists(): void
    {
        $redis = $this->makeMockRedis();
        $redis->method('exists')->with('key')->willReturn(0);

        $cache = new RedisDriver($redis);

        $this->assertFalse($cache->has('key'));
    }

    public function testInvalidKeyThrows(): void
    {
        $redis = $this->makeMockRedis();
        $cache = new RedisDriver($redis);

        $this->expectException(InvalidArgument::class);
        $cache->get('bad{key}');
    }

    public function testSetWithNegativeTtlDeletes(): void
    {
        $redis = $this->makeMockRedis();
        $redis->expects($this->once())->method('del')->with('key');

        $cache = new RedisDriver($redis);
        $cache->set('key', 'value', -1);
    }
}
