<?php

declare(strict_types=1);

namespace Arcanum\Test\Vault;

use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use Arcanum\Vault\NullDriver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(NullDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class NullDriverTest extends TestCase
{
    public function testGetAlwaysReturnsDefault(): void
    {
        $cache = new NullDriver();

        $cache->set('key', 'value');

        $this->assertNull($cache->get('key'));
        $this->assertSame('fallback', $cache->get('key', 'fallback'));
    }

    public function testSetDoesNotThrow(): void
    {
        $cache = new NullDriver();

        $result = $cache->set('key', 'value');

        $this->assertTrue($result);
    }

    public function testHasAlwaysReturnsFalse(): void
    {
        $cache = new NullDriver();

        $cache->set('key', 'value');

        $this->assertFalse($cache->has('key'));
    }

    public function testClearDoesNotThrow(): void
    {
        $cache = new NullDriver();

        $result = $cache->clear();

        $this->assertTrue($result);
    }

    public function testDeleteDoesNotThrow(): void
    {
        $cache = new NullDriver();

        $result = $cache->delete('key');

        $this->assertTrue($result);
    }

    public function testGetMultipleReturnsDefaults(): void
    {
        $cache = new NullDriver();

        $result = $cache->getMultiple(['a', 'b'], 'default');

        $this->assertSame(['a' => 'default', 'b' => 'default'], $result);
    }

    public function testInvalidKeyStillThrows(): void
    {
        $cache = new NullDriver();

        $this->expectException(InvalidArgument::class);
        $cache->get('bad{key}');
    }
}
