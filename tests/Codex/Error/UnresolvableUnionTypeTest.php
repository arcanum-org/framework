<?php

declare(strict_types=1);

namespace Arcanum\Test\Codex\Error;

use Arcanum\Codex\Error\UnresolvableUnionType;
use Arcanum\Glitch\ArcanumException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(UnresolvableUnionType::class)]
#[UsesClass(\Arcanum\Codex\Error\Unresolvable::class)]
#[UsesClass(ArcanumException::class)]
final class UnresolvableUnionTypeTest extends TestCase
{
    public function testUnresolvableUnionType(): void
    {
        // Arrange
        $exception = new UnresolvableUnionType(message: 'string');

        // Act
        $message = $exception->getMessage();

        // Assert
        $this->assertSame('Unresolvable Union Type: string', $message);
    }

    public function testGetTitle(): void
    {
        // Arrange & Act
        $exception = new UnresolvableUnionType('string|int');

        // Assert
        $this->assertSame('Unresolvable Union Type', $exception->getTitle());
    }
}
