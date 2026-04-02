<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\CacheSessionDriver;
use Arcanum\Vault\ArrayDriver;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheSessionDriver::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
final class CacheSessionDriverTest extends TestCase
{
    public function testWriteAndReadRoundTrip(): void
    {
        $driver = new CacheSessionDriver(new ArrayDriver());

        $driver->write('sess-1', ['_csrf' => 'token', '_identity' => 'user-1'], 3600);

        $data = $driver->read('sess-1');

        $this->assertSame('token', $data['_csrf']);
        $this->assertSame('user-1', $data['_identity']);
    }

    public function testReadReturnsEmptyArrayForMissingSession(): void
    {
        $driver = new CacheSessionDriver(new ArrayDriver());

        $this->assertSame([], $driver->read('nonexistent'));
    }

    public function testDestroyRemovesSession(): void
    {
        $driver = new CacheSessionDriver(new ArrayDriver());

        $driver->write('to-destroy', ['_csrf' => 'x'], 3600);
        $driver->destroy('to-destroy');

        $this->assertSame([], $driver->read('to-destroy'));
    }

    public function testGcDoesNotThrow(): void
    {
        $driver = new CacheSessionDriver(new ArrayDriver());

        $driver->gc(3600);

        $this->addToAssertionCount(1);
    }

    public function testKeysPrefixedToAvoidCollisions(): void
    {
        $cache = new ArrayDriver();
        $driver = new CacheSessionDriver($cache);

        $driver->write('my-session', ['_csrf' => 'val'], 3600);

        // The raw cache key includes the prefix.
        $this->assertTrue($cache->has('session.my-session'));
        $this->assertFalse($cache->has('my-session'));
    }
}
