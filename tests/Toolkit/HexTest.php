<?php

declare(strict_types=1);

namespace Arcanum\Test\Toolkit;

use Arcanum\Toolkit\Hex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Hex::class)]
final class HexTest extends TestCase
{
    public function testEncodeProducesLowercaseHex(): void
    {
        $hex = Hex::encode("\xDE\xAD\xBE\xEF");

        $this->assertSame('deadbeef', $hex);
    }

    public function testEncodeLengthIsDoubleInputLength(): void
    {
        $this->assertSame(40, strlen(Hex::encode(str_repeat("\x00", 20))));
    }

    public function testEncodeEmptyString(): void
    {
        $this->assertSame('', Hex::encode(''));
    }

    public function testIsValidAcceptsCorrectHex(): void
    {
        $this->assertTrue(Hex::isValid(str_repeat('ab', 20), 20));
    }

    public function testIsValidRejectsWrongLength(): void
    {
        $this->assertFalse(Hex::isValid('abcd', 20));
    }

    public function testIsValidRejectsNonHexCharacters(): void
    {
        $this->assertFalse(Hex::isValid(str_repeat('zz', 20), 20));
    }

    public function testIsValidRejectsUppercase(): void
    {
        $this->assertFalse(Hex::isValid(str_repeat('AB', 20), 20));
    }

    public function testIsValidRejectsEmptyString(): void
    {
        $this->assertFalse(Hex::isValid('', 20));
    }
}
