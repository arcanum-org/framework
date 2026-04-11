<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\SimpleIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SimpleIdentity::class)]
final class SimpleIdentityTest extends TestCase
{
    public function testIdReturnsConstructorValue(): void
    {
        $identity = new SimpleIdentity('user-42');

        $this->assertSame('user-42', $identity->id());
    }

    public function testRolesReturnsConstructorValues(): void
    {
        $identity = new SimpleIdentity('user-1', ['admin', 'editor']);

        $this->assertSame(['admin', 'editor'], $identity->roles());
    }

    public function testRolesDefaultsToEmptyArray(): void
    {
        $identity = new SimpleIdentity('user-1');

        $this->assertSame([], $identity->roles());
    }
}
