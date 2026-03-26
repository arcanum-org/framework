<?php

declare(strict_types=1);

namespace Arcanum\Test\Gather;

use Arcanum\Gather\Environment;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Environment::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
final class EnvironmentTest extends TestCase
{
    public function testSerializeReturnsEmptyArray(): void
    {
        // Arrange
        $environment = new Environment(['a' => 'b', 'c' => 'd']);

        // Act
        $serialized = serialize($environment);

        // Assert
        $this->assertSame('b', $environment['a']);
        $this->assertSame('O:26:"Arcanum\Gather\Environment":0:{}', $serialized);
    }

    public function testAttemptingToUnserializeThrowsLogicException(): void
    {
        // Arrange
        $serialized = 'O:26:"Arcanum\Gather\Environment":0:{}';

        // Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The environment cannot be unserialized');

        // Act
        unserialize($serialized);
    }

    public function testToStringReturnsENVIRONMENT(): void
    {
        // Arrange
        $environment = new Environment(['a' => 'b', 'c' => 'd']);

        // Act
        $string = (string)$environment;

        // Assert
        $this->assertSame('ENVIRONMENT', $string);
    }

    public function testJsonSerializeReturnsNULL(): void
    {
        // Arrange
        $environment = new Environment(['a' => 'b', 'c' => 'd']);

        // Act
        $json = json_encode($environment);

        // Assert
        $this->assertSame('null', $json);
    }

    public function testCloneThrowsLogicException(): void
    {
        // Arrange
        $environment = new Environment(['a' => 'b', 'c' => 'd']);

        // Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The environment cannot be cloned');

        // Act
        $_ = clone $environment;
    }
}
