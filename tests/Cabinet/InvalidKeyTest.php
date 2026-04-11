<?php

declare(strict_types=1);

namespace Arcanum\Test\Cabinet;

use Arcanum\Cabinet\InvalidKey;
use Arcanum\Glitch\ArcanumException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(InvalidKey::class)]
#[UsesClass(ArcanumException::class)]
final class InvalidKeyTest extends TestCase
{
    public function testInvalidKey(): void
    {
        // Arrange
        $invalidKey = new InvalidKey('foo');

        // Act
        $message = $invalidKey->getMessage();

        // Assert
        $this->assertEquals('Invalid Key: foo', $message);
    }

    public function testImplementsArcanumException(): void
    {
        // Arrange & Act
        $exception = new InvalidKey('foo');

        // Assert
        $this->assertInstanceOf(ArcanumException::class, $exception);
    }

    public function testGetTitle(): void
    {
        // Arrange & Act
        $exception = new InvalidKey('foo');

        // Assert
        $this->assertSame('Invalid Container Key', $exception->getTitle());
    }

    public function testGetSuggestion(): void
    {
        // Arrange & Act
        $exception = new InvalidKey('foo');

        // Assert
        $this->assertStringContainsString('strings', $exception->getSuggestion());
    }
}
