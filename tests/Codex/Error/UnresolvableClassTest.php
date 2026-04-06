<?php

declare(strict_types=1);

namespace Arcanum\Test\Codex\Error;

use Arcanum\Codex\Error\UnresolvableClass;
use Arcanum\Glitch\ArcanumException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(UnresolvableClass::class)]
#[UsesClass(\Arcanum\Codex\Error\Unresolvable::class)]
#[UsesClass(ArcanumException::class)]
final class UnresolvableClassTest extends TestCase
{
    public function testUnresolvableClass(): void
    {
        // Arrange
        $exception = new UnresolvableClass(message: 'foo');

        // Act
        $message = $exception->getMessage();

        // Assert
        $this->assertSame('Unresolvable Class: foo', $message);
    }

    public function testGetTitle(): void
    {
        // Arrange & Act
        $exception = new UnresolvableClass('Foo');

        // Assert
        $this->assertSame('Unresolvable Class', $exception->getTitle());
    }
}
