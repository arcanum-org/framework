<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\SessionId;
use Arcanum\Toolkit\Hex;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionId::class)]
#[UsesClass(Random::class)]
#[UsesClass(Hex::class)]
final class SessionIdTest extends TestCase
{
    public function testGenerateProducesValidHexString(): void
    {
        $id = SessionId::generate();

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{40}\z/', $id->value);
    }

    public function testTwoGeneratedIdsAreDifferent(): void
    {
        $a = SessionId::generate();
        $b = SessionId::generate();

        $this->assertNotSame($a->value, $b->value);
    }

    public function testFromStringAcceptsValidId(): void
    {
        $hex = str_repeat('ab', 20);
        $id = SessionId::fromString($hex);

        $this->assertNotNull($id);
        $this->assertSame($hex, $id->value);
    }

    public function testFromStringRejectsWrongLength(): void
    {
        $this->assertNull(SessionId::fromString('abc123'));
    }

    public function testFromStringRejectsNonHex(): void
    {
        $this->assertNull(SessionId::fromString(str_repeat('zz', 20)));
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->assertNull(SessionId::fromString(''));
    }

    public function testToStringReturnsValue(): void
    {
        $id = SessionId::generate();

        $this->assertSame($id->value, (string) $id);
    }
}
