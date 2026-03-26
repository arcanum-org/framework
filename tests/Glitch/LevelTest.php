<?php

declare(strict_types=1);

namespace Arcanum\Test\Glitch;

use Arcanum\Glitch\Level;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Level::class)]
final class LevelTest extends TestCase
{
    public function testIsDeprecationWithDeprecated(): void
    {
        $this->assertTrue(Level::isDeprecation(\E_DEPRECATED));
    }

    public function testIsDeprecationWithUserDeprecated(): void
    {
        $this->assertTrue(Level::isDeprecation(\E_USER_DEPRECATED));
    }

    public function testIsDeprecationWithEnumValue(): void
    {
        $this->assertTrue(Level::isDeprecation(Level::DEPRECATED));
        $this->assertTrue(Level::isDeprecation(Level::USER_DEPRECATED));
    }

    public function testIsDeprecationReturnsFalseForNonDeprecation(): void
    {
        $this->assertFalse(Level::isDeprecation(\E_ERROR));
        $this->assertFalse(Level::isDeprecation(\E_WARNING));
        $this->assertFalse(Level::isDeprecation(\E_NOTICE));
        $this->assertFalse(Level::isDeprecation(Level::ERROR));
    }

    public function testIsFatalWithError(): void
    {
        $this->assertTrue(Level::isFatal(\E_ERROR));
    }

    public function testIsFatalWithCoreError(): void
    {
        $this->assertTrue(Level::isFatal(\E_CORE_ERROR));
    }

    public function testIsFatalWithCompileError(): void
    {
        $this->assertTrue(Level::isFatal(\E_COMPILE_ERROR));
    }

    public function testIsFatalWithUserError(): void
    {
        $this->assertTrue(Level::isFatal(\E_USER_ERROR));
    }

    public function testIsFatalWithParse(): void
    {
        $this->assertTrue(Level::isFatal(\E_PARSE));
    }

    public function testIsFatalWithEnumValue(): void
    {
        $this->assertTrue(Level::isFatal(Level::ERROR));
        $this->assertTrue(Level::isFatal(Level::CORE_ERROR));
        $this->assertTrue(Level::isFatal(Level::COMPILE_ERROR));
        $this->assertTrue(Level::isFatal(Level::USER_ERROR));
        $this->assertTrue(Level::isFatal(Level::PARSE));
    }

    public function testIsFatalReturnsFalseForNonFatal(): void
    {
        $this->assertFalse(Level::isFatal(\E_WARNING));
        $this->assertFalse(Level::isFatal(\E_NOTICE));
        $this->assertFalse(Level::isFatal(\E_DEPRECATED));
        $this->assertFalse(Level::isFatal(Level::WARNING));
    }

    public function testIsDeprecationFallsBackToErrorForUnknownInt(): void
    {
        // Unknown int falls back to Level::ERROR via tryFrom, which is not a deprecation
        $this->assertFalse(Level::isDeprecation(99999));
    }

    public function testIsFatalFallsBackToErrorForUnknownInt(): void
    {
        // Unknown int falls back to Level::ERROR via tryFrom, which IS fatal
        $this->assertTrue(Level::isFatal(99999));
    }
}
