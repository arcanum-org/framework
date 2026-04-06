<?php

declare(strict_types=1);

namespace Arcanum\Test\Codex\Error;

use Arcanum\Codex\Error\UnknownClass;
use Arcanum\Glitch\ArcanumException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(UnknownClass::class)]
#[UsesClass(\Arcanum\Codex\Error\Unresolvable::class)]
#[UsesClass(ArcanumException::class)]
final class UnknownClassTest extends TestCase
{
    public function testUnknownClass(): void
    {
        // Arrange
        $unknownClass = new UnknownClass(message: 'foo');

        // Act
        $message = $unknownClass->getMessage();

        // Assert
        $this->assertSame('Unknown Class: foo', $message);
    }

    public function testGetTitle(): void
    {
        // Arrange & Act
        $exception = new UnknownClass('Foo');

        // Assert
        $this->assertSame('Unknown Class', $exception->getTitle());
    }
}
