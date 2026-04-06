<?php

declare(strict_types=1);

namespace Arcanum\Test\Cabinet;

use Arcanum\Cabinet\ServiceNotFound;
use Arcanum\Glitch\ArcanumException;
use Psr\Container\ContainerExceptionInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ServiceNotFound::class)]
#[UsesClass(ArcanumException::class)]
final class ServiceNotFoundTest extends TestCase
{
    public function testDefaultMessage(): void
    {
        // Arrange & Act
        $exception = new ServiceNotFound('App\Service\Mailer');

        // Assert
        $this->assertSame("Service 'App\Service\Mailer' not found", $exception->getMessage());
    }

    public function testCustomMessage(): void
    {
        // Arrange & Act
        $exception = new ServiceNotFound('Mailer', 'Custom error message');

        // Assert
        $this->assertSame('Custom error message', $exception->getMessage());
    }

    public function testGetServiceName(): void
    {
        // Arrange & Act
        $exception = new ServiceNotFound('App\Service\Mailer');

        // Assert
        $this->assertSame('App\Service\Mailer', $exception->getServiceName());
    }

    public function testGetTitle(): void
    {
        // Arrange & Act
        $exception = new ServiceNotFound('Mailer');

        // Assert
        $this->assertSame('Service Not Found', $exception->getTitle());
    }

    public function testGetSuggestionIsNullByDefault(): void
    {
        // Arrange & Act
        $exception = new ServiceNotFound('Mailer');

        // Assert
        $this->assertNull($exception->getSuggestion());
    }

    public function testWithSuggestion(): void
    {
        // Arrange & Act
        $exception = (new ServiceNotFound('Mailer'))
            ->withSuggestion('Did you register it?');

        // Assert
        $this->assertSame('Did you register it?', $exception->getSuggestion());
    }

    public function testImplementsArcanumException(): void
    {
        // Arrange & Act
        $exception = new ServiceNotFound('Mailer');

        // Assert
        $this->assertInstanceOf(ArcanumException::class, $exception);
    }

    public function testImplementsContainerExceptionInterface(): void
    {
        // Arrange & Act
        $exception = new ServiceNotFound('Mailer');

        // Assert
        $this->assertInstanceOf(ContainerExceptionInterface::class, $exception);
    }

    public function testExtendsInvalidArgumentException(): void
    {
        // Arrange & Act
        $exception = new ServiceNotFound('Mailer');

        // Assert
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testPreviousExceptionIsPreserved(): void
    {
        // Arrange
        $previous = new \RuntimeException('root cause');

        // Act
        $exception = new ServiceNotFound('Mailer', 'fail', $previous);

        // Assert
        $this->assertSame($previous, $exception->getPrevious());
    }
}
