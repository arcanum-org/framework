<?php

declare(strict_types=1);

namespace Arcanum\Test\Codex\Error;

use Arcanum\Codex\Error\Unresolvable;
use Arcanum\Glitch\ArcanumException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Unresolvable::class)]
#[UsesClass(ArcanumException::class)]
final class UnresolvableTest extends TestCase
{
    public function testUnresolvable(): void
    {
        // Arrange
        $unresolvable = new Unresolvable('foo');

        // Act & Assert
        $this->assertInstanceOf(\InvalidArgumentException::class, $unresolvable);
        $this->assertInstanceOf(\Psr\Container\ContainerExceptionInterface::class, $unresolvable);
        $this->assertSame('foo', $unresolvable->getMessage());
    }

    public function testImplementsArcanumException(): void
    {
        // Arrange & Act
        $exception = new Unresolvable('foo');

        // Assert
        $this->assertInstanceOf(ArcanumException::class, $exception);
        $this->assertSame('Unresolvable Dependency', $exception->getTitle());
        $this->assertNull($exception->getSuggestion());
    }

    public function testWithSuggestion(): void
    {
        // Arrange & Act
        $exception = (new Unresolvable('foo'))
            ->withSuggestion('Try this instead');

        // Assert
        $this->assertSame('Try this instead', $exception->getSuggestion());
    }
}
