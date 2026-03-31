<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Rune\ExitCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ExitCode::class)]
final class ExitCodeTest extends TestCase
{
    public function testSuccessIsZero(): void
    {
        $this->assertSame(0, ExitCode::Success->value);
    }

    public function testFailureIsOne(): void
    {
        $this->assertSame(1, ExitCode::Failure->value);
    }

    public function testInvalidIsTwo(): void
    {
        $this->assertSame(2, ExitCode::Invalid->value);
    }
}
