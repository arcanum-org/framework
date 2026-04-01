<?php

declare(strict_types=1);

namespace Arcanum\Test\Toolkit\Hashing;

use Arcanum\Toolkit\Hashing\BcryptHasher;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BcryptHasher::class)]
final class BcryptHasherTest extends TestCase
{
    public function testHashVerifyRoundTrip(): void
    {
        $hasher = new BcryptHasher();
        $password = 'correct-horse-battery-staple';

        $hash = $hasher->hash($password);

        $this->assertTrue($hasher->verify($password, $hash));
    }

    public function testWrongValueReturnsFalse(): void
    {
        $hasher = new BcryptHasher();

        $hash = $hasher->hash('real-password');

        $this->assertFalse($hasher->verify('wrong-password', $hash));
    }

    public function testHashOutputIsValidBcryptString(): void
    {
        $hasher = new BcryptHasher();

        $hash = $hasher->hash('test');

        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function testNeedsRehashReturnsTrueWhenCostChanges(): void
    {
        $hasher4 = new BcryptHasher(cost: 4);
        $hasher10 = new BcryptHasher(cost: 10);

        $hash = $hasher4->hash('test');

        $this->assertTrue($hasher10->needsRehash($hash));
    }

    public function testNeedsRehashReturnsFalseForCurrentParams(): void
    {
        $hasher = new BcryptHasher(cost: 4);

        $hash = $hasher->hash('test');

        $this->assertFalse($hasher->needsRehash($hash));
    }
}
