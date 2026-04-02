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

    public function testGetReturnsCachedValue(): void
    {
        // Arrange
        $redis = $this->createStub(\Redis::class);
        $redis->method('get')->willReturn(serialize('value'));

        $cache = new RedisDriver($redis);

        // Act & Assert
        $this->assertSame('value', $cache->get('key'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        // Arrange
        $redis = $this->createStub(\Redis::class);
        $redis->method('get')->willReturn(false);

        $cache = new RedisDriver($redis);

        // Act & Assert
        $this->assertSame('default', $cache->get('key', 'default'));
    }

    public function testSetCallsRedisSet(): void
    {
        // Arrange
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('set')
            ->with('key', serialize('value'))
            ->willReturn(true);

        $cache = new RedisDriver($redis);

        // Act
        $cache->set('key', 'value');
    }

    public function testSetWithTtlCallsSetex(): void
    {
        // Arrange
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('setex')
            ->with('key', 3600, serialize('value'))
            ->willReturn(true);

        $cache = new RedisDriver($redis);

        // Act
        $cache->set('key', 'value', 3600);
    }

    public function testDeleteCallsDel(): void
    {
        // Arrange
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('del')
            ->with('key');

        $cache = new RedisDriver($redis);

        // Act
        $cache->delete('key');
    }

    public function testClearCallsFlushDb(): void
    {
        // Arrange
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('flushDB')
            ->willReturn(true);

        $cache = new RedisDriver($redis);

        // Act
        $cache->clear();
    }

    public function testHasChecksExists(): void
    {
        // Arrange
        $redis = $this->createStub(\Redis::class);
        $redis->method('exists')->willReturn(1);

        $cache = new RedisDriver($redis);

        // Act & Assert
        $this->assertTrue($cache->has('key'));
    }

    public function testHasReturnsFalseWhenNotExists(): void
    {
        // Arrange
        $redis = $this->createStub(\Redis::class);
        $redis->method('exists')->willReturn(0);

        $cache = new RedisDriver($redis);

        // Act & Assert
        $this->assertFalse($cache->has('key'));
    }

    public function testInvalidKeyThrows(): void
    {
        // Arrange
        $redis = $this->createStub(\Redis::class);
        $cache = new RedisDriver($redis);

        // Act & Assert
        $this->expectException(InvalidArgument::class);
        $cache->get('bad{key}');
    }

    public function testSetWithNegativeTtlDeletes(): void
    {
        // Arrange
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('del')->with('key');

        $cache = new RedisDriver($redis);

        // Act
        $cache->set('key', 'value', -1);
    }
}
