<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\CsrfToken;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsrfToken::class)]
#[UsesClass(Random::class)]
final class CsrfTokenTest extends TestCase
{
    public function testGenerateProducesValidHexString(): void
    {
        $token = CsrfToken::generate();

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $token->value);
    }

    public function testFromStringRestoresValue(): void
    {
        $hex = str_repeat('ab', 32);
        $token = CsrfToken::fromString($hex);

        $this->assertSame($hex, $token->value);
    }

    public function testMatchesReturnsTrueForSameValue(): void
    {
        $token = CsrfToken::generate();

        $this->assertTrue($token->matches($token->value));
    }

    public function testMatchesReturnsFalseForDifferentValue(): void
    {
        $token = CsrfToken::generate();

        $this->assertFalse($token->matches('wrong'));
    }

    public function testMatchesReturnsFalseForEmptyString(): void
    {
        $token = CsrfToken::generate();

        $this->assertFalse($token->matches(''));
    }

    public function testToStringReturnsValue(): void
    {
        $token = CsrfToken::generate();

        $this->assertSame($token->value, (string) $token);
    }
}
