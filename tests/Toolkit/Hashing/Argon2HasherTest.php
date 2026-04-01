<?php

declare(strict_types=1);

namespace Arcanum\Test\Toolkit\Hashing;

use Arcanum\Toolkit\Hashing\Argon2Hasher;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Argon2Hasher::class)]
final class Argon2HasherTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2id is not available in this PHP build.');
        }
    }

    public function testHashVerifyRoundTrip(): void
    {
        $hasher = new Argon2Hasher();
        $password = 'correct-horse-battery-staple';

        $hash = $hasher->hash($password);

        $this->assertTrue($hasher->verify($password, $hash));
    }

    public function testWrongValueReturnsFalse(): void
    {
        $hasher = new Argon2Hasher();

        $hash = $hasher->hash('real-password');

        $this->assertFalse($hasher->verify('wrong-password', $hash));
    }

    public function testHashOutputIsValidArgon2idString(): void
    {
        $hasher = new Argon2Hasher();

        $hash = $hasher->hash('test');

        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function testNeedsRehashDetectsStaleParams(): void
    {
        $lowCost = new Argon2Hasher(memoryCost: 1024, timeCost: 1);
        $highCost = new Argon2Hasher(memoryCost: 65536, timeCost: 4);

        $hash = $lowCost->hash('test');

        $this->assertTrue($highCost->needsRehash($hash));
    }

    public function testNeedsRehashReturnsFalseForCurrentParams(): void
    {
        $hasher = new Argon2Hasher(memoryCost: 1024, timeCost: 1);

        $hash = $hasher->hash('test');

        $this->assertFalse($hasher->needsRehash($hash));
    }
}
