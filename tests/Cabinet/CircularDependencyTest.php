<?php

declare(strict_types=1);

namespace Arcanum\Test\Cabinet;

use Arcanum\Cabinet\CircularDependency;
use Arcanum\Glitch\ArcanumException;
use Psr\Container\ContainerExceptionInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CircularDependency::class)]
#[UsesClass(ArcanumException::class)]
final class CircularDependencyTest extends TestCase
{
    public function testMessageIncludesChain(): void
    {
        // Arrange & Act
        $exception = new CircularDependency(['A', 'B', 'C', 'A']);

        // Assert
        $this->assertSame(
            'Circular dependency detected: A → B → C → A',
            $exception->getMessage(),
        );
    }

    public function testGetChain(): void
    {
        // Arrange & Act
        $exception = new CircularDependency(['A', 'B', 'A']);

        // Assert
        $this->assertSame(['A', 'B', 'A'], $exception->getChain());
    }

    public function testGetTitle(): void
    {
        // Arrange & Act
        $exception = new CircularDependency(['A', 'B', 'A']);

        // Assert
        $this->assertSame('Circular Dependency', $exception->getTitle());
    }

    public function testGetSuggestionIncludesChain(): void
    {
        // Arrange & Act
        $exception = new CircularDependency(['A', 'B', 'C', 'A']);

        // Assert
        $suggestion = $exception->getSuggestion();
        $this->assertNotNull($suggestion);
        $this->assertStringContainsString('A → B → C → A', $suggestion);
    }

    public function testImplementsArcanumException(): void
    {
        // Arrange & Act
        $exception = new CircularDependency(['A', 'A']);

        // Assert
        $this->assertInstanceOf(ArcanumException::class, $exception);
    }

    public function testImplementsContainerExceptionInterface(): void
    {
        // Arrange & Act
        $exception = new CircularDependency(['A', 'A']);

        // Assert
        $this->assertInstanceOf(ContainerExceptionInterface::class, $exception);
    }

    public function testExtendsRuntimeException(): void
    {
        // Arrange & Act
        $exception = new CircularDependency(['A', 'A']);

        // Assert
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testPreviousExceptionIsPreserved(): void
    {
        // Arrange
        $previous = new \RuntimeException('root cause');

        // Act
        $exception = new CircularDependency(['A', 'A'], $previous);

        // Assert
        $this->assertSame($previous, $exception->getPrevious());
    }
}
