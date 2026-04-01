<?php

declare(strict_types=1);

namespace Arcanum\Test\Toolkit;

use Arcanum\Toolkit\Random;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Random::class)]
final class RandomTest extends TestCase
{
    public function testBytesReturnsCorrectLength(): void
    {
        $this->assertSame(16, strlen(Random::bytes(16)));
        $this->assertSame(32, strlen(Random::bytes(32)));
        $this->assertSame(64, strlen(Random::bytes(64)));
    }

    public function testHexOutputLengthIsBytesTimesTwo(): void
    {
        $hex = Random::hex(32);

        $this->assertSame(64, strlen($hex));
    }

    public function testHexContainsOnlyHexCharacters(): void
    {
        $hex = Random::hex(32);

        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $hex);
    }

    public function testHexDefaultIs32Bytes(): void
    {
        $hex = Random::hex();

        $this->assertSame(64, strlen($hex));
    }

    public function testBase64urlContainsOnlyUrlSafeCharacters(): void
    {
        $value = Random::base64url(32);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $value);
    }

    public function testBase64urlDefaultIs32Bytes(): void
    {
        // 32 bytes → 43 base64url characters (no padding).
        $value = Random::base64url();

        $this->assertSame(43, strlen($value));
    }

    public function testTwoCallsProduceDifferentValues(): void
    {
        $this->assertNotSame(Random::bytes(32), Random::bytes(32));
        $this->assertNotSame(Random::hex(32), Random::hex(32));
        $this->assertNotSame(Random::base64url(32), Random::base64url(32));
    }
}
