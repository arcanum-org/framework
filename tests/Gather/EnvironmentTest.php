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

    public function testGetReturnsValue(): void
    {
        // Arrange
        $environment = new Environment(['DB_HOST' => 'localhost', 'DB_PORT' => '5432']);

        // Act & Assert
        $this->assertSame('localhost', $environment->get('DB_HOST'));
        $this->assertSame('5432', $environment->get('DB_PORT'));
        $this->assertNull($environment->get('MISSING'));
    }

    public function testHasChecksExistence(): void
    {
        // Arrange
        $environment = new Environment(['APP_KEY' => 'secret']);

        // Act & Assert
        $this->assertTrue($environment->has('APP_KEY'));
        $this->assertFalse($environment->has('MISSING'));
    }

    public function testSetStoresValue(): void
    {
        // Arrange
        $environment = new Environment();

        // Act
        $environment->set('REDIS_HOST', '127.0.0.1');

        // Assert
        $this->assertSame('127.0.0.1', $environment->get('REDIS_HOST'));
        $this->assertTrue($environment->has('REDIS_HOST'));
    }

    public function testCountReturnsNumberOfEntries(): void
    {
        // Arrange
        $empty = new Environment();
        $populated = new Environment(['a' => '1', 'b' => '2', 'c' => '3']);

        // Act & Assert
        $this->assertCount(0, $empty);
        $this->assertCount(3, $populated);
    }
}
