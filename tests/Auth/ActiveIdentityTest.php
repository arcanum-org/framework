<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\SimpleIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveIdentity::class)]
#[UsesClass(SimpleIdentity::class)]
final class ActiveIdentityTest extends TestCase
{
    public function testGetThrowsWhenEmpty(): void
    {
        $active = new ActiveIdentity();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No authenticated identity');
        $active->get();
    }

    public function testResolveReturnsNullWhenEmpty(): void
    {
        $active = new ActiveIdentity();

        $this->assertNull($active->resolve());
    }

    public function testHasReturnsFalseWhenEmpty(): void
    {
        $active = new ActiveIdentity();

        $this->assertFalse($active->has());
    }

    public function testSetAndGetRoundTrip(): void
    {
        $active = new ActiveIdentity();
        $identity = new SimpleIdentity('user-1');

        $active->set($identity);

        $this->assertTrue($active->has());
        $this->assertSame($identity, $active->get());
        $this->assertSame($identity, $active->resolve());
    }
}
