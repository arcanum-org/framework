<?php

declare(strict_types=1);

namespace Arcanum\Test\Codex\Error;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\Arcanum\Codex\Error\Unresolvable::class)]
final class UnresolvableTest extends TestCase
{
    public function testUnresolvable(): void
    {
        // Arrange
        $unresolvable = new \Arcanum\Codex\Error\Unresolvable('foo');

        // Act & Assert
        $this->assertInstanceOf(\InvalidArgumentException::class, $unresolvable);
        $this->assertInstanceOf(\Psr\Container\ContainerExceptionInterface::class, $unresolvable);
        $this->assertSame('foo', $unresolvable->getMessage());
    }
}
