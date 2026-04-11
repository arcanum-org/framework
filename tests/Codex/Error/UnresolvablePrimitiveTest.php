<?php

declare(strict_types=1);

namespace Arcanum\Test\Codex\Error;

use Arcanum\Codex\Error\UnresolvablePrimitive;
use Arcanum\Glitch\ArcanumException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(UnresolvablePrimitive::class)]
#[UsesClass(\Arcanum\Codex\Error\Unresolvable::class)]
#[UsesClass(ArcanumException::class)]
final class UnresolvablePrimitiveTest extends TestCase
{
    public function testUnresolvablePrimitive(): void
    {
        // Arrange
        $exception = new UnresolvablePrimitive(message: 'string');

        // Act
        $message = $exception->getMessage();

        // Assert
        $this->assertSame('Unresolvable Primitive: string', $message);
    }

    public function testGetTitle(): void
    {
        // Arrange & Act
        $exception = new UnresolvablePrimitive('foo');

        // Assert
        $this->assertSame('Unresolvable Parameter', $exception->getTitle());
    }
}
