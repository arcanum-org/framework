<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\MethodNotAllowed;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MethodNotAllowed::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(StatusCode::class)]
final class MethodNotAllowedTest extends TestCase
{
    public function testStatusCodeIs405(): void
    {
        // Arrange & Act
        $exception = new MethodNotAllowed(['GET']);

        // Assert
        $this->assertSame(StatusCode::MethodNotAllowed, $exception->getStatusCode());
        $this->assertSame(405, $exception->getCode());
    }

    public function testAllowedMethodsAreAccessible(): void
    {
        // Arrange & Act
        $exception = new MethodNotAllowed(['GET', 'POST']);

        // Assert
        $this->assertSame(['GET', 'POST'], $exception->getAllowedMethods());
    }

    public function testDefaultMessageListsAllowedMethods(): void
    {
        // Arrange & Act
        $exception = new MethodNotAllowed(['PUT', 'POST', 'PATCH', 'DELETE']);

        // Assert
        $this->assertSame(
            'Method not allowed. Allowed methods: PUT, POST, PATCH, DELETE.',
            $exception->getMessage(),
        );
    }

    public function testCustomMessageOverridesDefault(): void
    {
        // Arrange & Act
        $exception = new MethodNotAllowed(['GET'], 'Custom message.');

        // Assert
        $this->assertSame('Custom message.', $exception->getMessage());
    }

    public function testExtendsHttpException(): void
    {
        // Arrange & Act
        $exception = new MethodNotAllowed(['GET']);

        // Assert
        $this->assertInstanceOf(HttpException::class, $exception);
    }
}
